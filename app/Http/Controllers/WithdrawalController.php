<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use GuzzleHttp\Client;

class WithdrawalController extends Controller
{
    /**
     * Request wallet withdrawal
     */
    public function requestWithdrawal(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10|max:10000',
            'gateway' => 'required|in:paymob,paypal',
            'account_details' => 'required|array',
            'account_details.email' => 'required_if:gateway,paypal|email',
            'account_details.phone' => 'required_if:gateway,paymob|string|min:10|max:15',
            'account_details.name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = $request->user();
        if (!$user || $user->status !== 'active') {
            return $this->error('Authentication required and account must be active.', 401);
        }

        $amount = $request->amount;
        $gateway = $request->gateway;
        $accountDetails = $request->account_details;

        try {
            return DB::transaction(function () use ($user, $amount, $gateway, $accountDetails) {
                // --- Monthly withdrawal limit (3 per month) ---
                $currentMonth = Carbon::now()->startOfMonth();
                $withdrawalCount = WalletTransaction::where('wallet_id', function ($q) use ($user) {
                    $q->select('id')->from('wallets')->where('user_id', $user->id);
                })
                ->where('type', 'withdrawal')
                ->where('created_at', '>=', $currentMonth)
                ->count();

                if ($withdrawalCount >= 3) {
                    throw new Exception('Monthly withdrawal limit reached (3 transactions per month).');
                }

                // --- Lock wallet ---
                $wallet = Wallet::lockForUpdate()->where('user_id', $user->id)->first();
                if (!$wallet) {
                    throw new Exception('Wallet not found.');
                }

                if (bccomp($wallet->balance, $amount, 2) < 0) {
                    throw new Exception('Insufficient wallet balance.');
                }

                // --- Create withdrawal record ---
                $withdrawal = WithdrawalRequest::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'gateway' => $gateway,
                    'account_details' => $accountDetails,
                    'status' => 'processing',
                    'transaction_ref' => Str::uuid()->toString(),
                    'requested_at' => Carbon::now(),
                ]);

                // --- Deduct wallet balance ---
                $balanceBefore = $wallet->balance;
                $wallet->balance = bcsub($wallet->balance, $amount, 2);
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'amount' => '-' . $amount,
                    'type' => 'withdrawal',
                    'ref_id' => $withdrawal->id,
                    'ref_type' => 'withdrawal_request',
                    'description' => "Withdrawal via {$gateway}",
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->balance,
                ]);

                // --- Process gateway payout ---
                $result = $this->processWithdrawalPayment($withdrawal);

                if ($result['success']) {
                    $withdrawal->update([
                        'status' => 'completed',
                        'completed_at' => Carbon::now(),
                        'gateway_response' => $result['data'],
                    ]);

                    return $this->success('Withdrawal processed successfully.', [
                        'withdrawal' => $withdrawal->fresh(),
                        'new_balance' => $wallet->fresh()->balance,
                    ]);
                } else {
                    // --- Refund wallet if payout fails ---
                    $wallet->balance = bcadd($wallet->balance, $amount, 2);
                    $wallet->save();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'type' => 'refund',
                        'ref_id' => $withdrawal->id,
                        'ref_type' => 'withdrawal_request',
                        'description' => 'Withdrawal failed - amount refunded',
                        'balance_before' => bcsub($wallet->balance, $amount, 2),
                        'balance_after' => $wallet->balance,
                    ]);

                    $withdrawal->update([
                        'status' => 'failed',
                        'gateway_response' => $result['error'],
                    ]);

                    throw new Exception('Withdrawal failed: ' . $result['error']);
                }
            });
        } catch (Exception $e) {
            Log::error('Withdrawal error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'amount' => $amount,
                'gateway' => $gateway,
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Process gateway payment
     */
    private function processWithdrawalPayment($withdrawal)
    {
        try {
            return match ($withdrawal->gateway) {
                'paymob' => $this->processPaymobWithdrawal($withdrawal),
                'paypal' => $this->processPaypalWithdrawal($withdrawal),
                default => ['success' => false, 'error' => 'Unsupported gateway'],
            };
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Paymob withdrawal
     */
private function processPaymobWithdrawal($withdrawal)
{
    if (app()->environment('local', 'testing')) {
        // --- Simulate success ---
        return [
            'success' => true,
            'data' => [
                'simulated' => true,
                'gateway' => 'paymob',
                'message' => 'Simulated Paymob payout success (DEV mode)',
                'transaction_ref' => $withdrawal->transaction_ref,
            ]
        ];
    }

    // --- Real API call for production/staging ---
    $config = config('services.paymob');
    $client = new Client();
    try {
        $authResponse = $client->post($config['base_url'] . '/auth/tokens', [
            'json' => ['api_key' => $config['api_key']],
        ]);
        $token = json_decode($authResponse->getBody(), true)['token'];

        $payoutResponse = $client->post($config['base_url'] . '/payouts', [
            'headers' => ['Authorization' => "Bearer {$token}"],
            'json' => [
                'amount_cents' => bcmul($withdrawal->amount, 100, 0),
                'currency' => 'EGP',
                'recipient' => [
                    'name' => $withdrawal->account_details['name'],
                    'phone_number' => $withdrawal->account_details['phone'],
                ],
                'external_id' => $withdrawal->transaction_ref,
            ],
        ]);

        $payoutData = json_decode($payoutResponse->getBody(), true);

        return ($payoutData['success'] ?? false)
            ? ['success' => true, 'data' => $payoutData]
            : ['success' => false, 'error' => $payoutData['message'] ?? 'Paymob payout failed'];
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        return ['success' => false, 'error' => 'Paymob API error: ' . $e->getMessage()];
    }
}
    /**
     * PayPal withdrawal
     */
private function processPaypalWithdrawal($withdrawal)
{
    if (app()->environment('local', 'testing')) {
        // --- Simulate success ---
        return [
            'success' => true,
            'data' => [
                'simulated' => true,
                'gateway' => 'paypal',
                'message' => 'Simulated PayPal payout success (DEV mode)',
                'transaction_ref' => $withdrawal->transaction_ref,
            ]
        ];
    }

    // --- Real API call ---
    $config = config('services.paypal');
    $client = new Client();
    try {
        // ... keep your real PayPal payout code here ...
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        // ... handle errors ...
    }
}

    /**
     * Withdrawal history
     */
    public function getWithdrawalHistory(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 10), 50);

        $withdrawals = WithdrawalRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $currentMonth = Carbon::now()->startOfMonth();
        $count = WithdrawalRequest::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->count();

        return $this->success('Withdrawal history retrieved.', [
            'withdrawals' => $withdrawals,
            'rate_limit' => [
                'used' => $count,
                'limit' => 3,
                'remaining' => max(0, 3 - $count),
                'reset_date' => $currentMonth->copy()->addMonth()->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Withdrawal info
     */
    public function getWithdrawalInfo(Request $request)
    {
        $user = $request->user();
        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $wallet->balance : '0.00';

        $currentMonth = Carbon::now()->startOfMonth();
        $count = WithdrawalRequest::where('user_id', $user->id)
            ->where('created_at', '>=', $currentMonth)
            ->count();

        return $this->success('Withdrawal info retrieved.', [
            'wallet_balance' => $balance,
            'min_withdrawal' => '10.00',
            'max_withdrawal' => '10000.00',
            'rate_limit' => [
                'used' => $count,
                'limit' => 3,
                'remaining' => max(0, 3 - $count),
                'reset_date' => $currentMonth->copy()->addMonth()->format('Y-m-d'),
            ],
            'can_withdraw' => $count < 3 && bccomp($balance, '10', 2) >= 0,
            'supported_gateways' => ['paymob', 'paypal'],
        ]);
    }

    /**
     * Helpers for responses
     */
    protected function success($message, $data = [], $status = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function error($message, $status = 400, $errors = [])
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}