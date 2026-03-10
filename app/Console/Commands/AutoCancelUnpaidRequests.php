<?php

namespace App\Console\Commands;

use App\Http\Controllers\RentRequestController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class AutoCancelUnpaidRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rent-requests:auto-cancel 
                            {--dry-run : Show what would be cancelled without actually cancelling}
                            {--limit=100 : Maximum number of requests to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel unpaid confirmed rent requests past their payment deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Starting auto-cancel job for unpaid rent requests...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No actual cancellations will be performed');
        }

        try {
            $controller = new RentRequestController();

            if ($isDryRun) {
                // For dry run, we'd need to modify the controller method or create a separate dry-run method
                $this->handleDryRun($limit);
            } else {
                // Call the actual auto-cancel method
                $response = $controller->autoCancelUnpaidRequests();
                $data = $response->getData(true);

                if ($data['success']) {
                    $stats = $data['data'];
                    $this->info("Successfully processed auto-cancel job:");
                    $this->info("- Total expired: {$stats['total_expired']}");
                    $this->info("- Successfully cancelled: {$stats['cancelled']}");

                    if ($stats['cancelled'] > 0) {
                        Log::info('Auto-cancel command completed', $stats);
                    }
                } else {
                    $this->error("Auto-cancel job failed: {$data['message']}");
                    return SymfonyCommand::FAILURE;
                }
            }

            return SymfonyCommand::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Auto-cancel command failed: {$e->getMessage()}");
            Log::error('Auto-cancel command exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return SymfonyCommand::FAILURE;
        }
    }

    /**
     * Handle dry run mode
     */
    private function handleDryRun(int $limit)
    {
        $expiredRequests = \App\Models\RentRequest::where('status', 'confirmed')
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<', now())
            ->with(['property:id,title', 'user:id,name,email'])
            ->limit($limit)
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('No expired requests found.');
            return;
        }

        $this->info("Found {$expiredRequests->count()} expired requests that would be cancelled:");

        $this->table(
            ['ID', 'Property', 'User', 'Deadline', 'Days Overdue'],
            $expiredRequests->map(function ($request) {
                $daysOverdue = now()->diffInDays($request->payment_deadline);
                return [
                    $request->id,
                    $request->property->title ?? 'N/A',
                    $request->user->name ?? 'N/A',
                    $request->payment_deadline->format('Y-m-d H:i:s'),
                    $daysOverdue,
                ];
            })
        );

        $this->warn('Use without --dry-run to actually cancel these requests.');
    }
}