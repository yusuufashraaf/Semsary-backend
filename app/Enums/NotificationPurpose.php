<?php

namespace App\Enums;

enum NotificationPurpose: string
{
    // User verification cases
    case USER_VERIFY_REQUEST = 'user_verify_request';
    case USER_VERIFIED = 'user_verified';
    case USER_VERIFICATION_REJECTED = 'user_verification_rejected';

    // Property verification cases
    case PROPERTY_VERIFY_REQUEST = 'property_verify_request';
    case PROPERTY_VERIFIED = 'property_verified';
    case PROPERTY_VERIFICATION_REJECTED = 'property_verification_rejected';

    // Property transactions
    case PROPERTY_RENT_ACCEPTED = 'property_rent_accepted';
    case PROPERTY_BUY_ACCEPTED = 'property_buy_accepted';

    // Rent request related
    case RENT_REQUESTED = 'rent_requested';
    case RENT_REQUEST_ACCEPTED = 'rent_request_accepted';
    case OWNER_REJECTS_REQUEST = 'owner_rejects_request';
    case USER_REJECTS_REQUEST = 'user_rejects_request';
    case REQUEST_AUTO_CANCELLED = 'request_auto_cancelled';
    case REQUEST_AUTO_CANCELLED_BY_SYSTEM = 'request_auto_cancelled_by_system';

    // Payment related
    case PAYMENT_SUCCESSFUL = 'payment_successful';
    case PAYMENT_FAILED = 'payment_failed';

    // Checkout related
    case CHECKOUT_REQUESTED = 'checkout_requested';
    case CHECKOUT_AWAITING_OWNER_CONFIRMATION = 'checkout_awaiting_owner_confirmation';
    case CHECKOUT_REQUIRES_REVIEW = 'checkout_requires_review';
    case CHECKOUT_COMPLETED = 'checkout_completed';
    case CHECKOUT_DISPUTE = 'checkout_dispute';
    case CHECKOUT_PAYOUT_PROCESSED = 'checkout_payout_processed';
    case CHECKOUT_REFUND_PROCESSED = 'checkout_refund_processed';

    // Authentication related
    case EMAIL_OTP = 'email_otp';
    case PASSWORD_RESET = 'password_reset';

    /**
     * Get a human-readable label for the purpose
     */
    public function label(): string
    {
        return match($this) {
            self::USER_VERIFY_REQUEST => 'User Verification Request',
            self::USER_VERIFIED => 'User Verified',
            self::USER_VERIFICATION_REJECTED => 'User Verification Rejected',
            self::PROPERTY_VERIFY_REQUEST => 'Property Verification Request',
            self::PROPERTY_VERIFIED => 'Property Verified',
            self::PROPERTY_VERIFICATION_REJECTED => 'Property Verification Rejected',
            self::PROPERTY_RENT_ACCEPTED => 'Property Rent Accepted',
            self::PROPERTY_BUY_ACCEPTED => 'Property Buy Accepted',
            self::RENT_REQUESTED => 'Rent Requested',
            self::RENT_REQUEST_ACCEPTED => 'Rent Request Accepted',
            self::OWNER_REJECTS_REQUEST => 'Owner Rejects Request',
            self::USER_REJECTS_REQUEST => 'User Rejects Request',
            self::REQUEST_AUTO_CANCELLED => 'Request Auto Cancelled',
            self::REQUEST_AUTO_CANCELLED_BY_SYSTEM => 'Request Auto Cancelled by System',
            self::PAYMENT_SUCCESSFUL => 'Payment Successful',
            self::PAYMENT_FAILED => 'Payment Failed',
            self::CHECKOUT_REQUESTED => 'Checkout Requested',
            self::CHECKOUT_AWAITING_OWNER_CONFIRMATION => 'Checkout Awaiting Owner Confirmation',
            self::CHECKOUT_REQUIRES_REVIEW => 'Checkout Requires Review',
            self::CHECKOUT_COMPLETED => 'Checkout Completed',
            self::CHECKOUT_DISPUTE => 'Checkout Dispute',
            self::CHECKOUT_PAYOUT_PROCESSED => 'Checkout Payout Processed',
            self::CHECKOUT_REFUND_PROCESSED => 'Checkout Refund Processed',
            self::EMAIL_OTP => 'Email OTP',
            self::PASSWORD_RESET => 'Password Reset',
        };
    }

    /**
     * Get all purposes grouped by category
     */
    public static function getByCategory(): array
    {
        return [
            'user_verification' => [
                self::USER_VERIFY_REQUEST,
                self::USER_VERIFIED,
                self::USER_VERIFICATION_REJECTED,
            ],
            'property_verification' => [
                self::PROPERTY_VERIFY_REQUEST,
                self::PROPERTY_VERIFIED,
                self::PROPERTY_VERIFICATION_REJECTED,
            ],
            'property_transactions' => [
                self::PROPERTY_RENT_ACCEPTED,
                self::PROPERTY_BUY_ACCEPTED,
            ],
            'rent_requests' => [
                self::RENT_REQUESTED,
                self::RENT_REQUEST_ACCEPTED,
                self::OWNER_REJECTS_REQUEST,
                self::USER_REJECTS_REQUEST,
                self::REQUEST_AUTO_CANCELLED,
                self::REQUEST_AUTO_CANCELLED_BY_SYSTEM,
            ],
            'payments' => [
                self::PAYMENT_SUCCESSFUL,
                self::PAYMENT_FAILED,
            ],
            'checkouts' => [
                self::CHECKOUT_REQUESTED,
                self::CHECKOUT_AWAITING_OWNER_CONFIRMATION,
                self::CHECKOUT_REQUIRES_REVIEW,
                self::CHECKOUT_COMPLETED,
                self::CHECKOUT_DISPUTE,
                self::CHECKOUT_PAYOUT_PROCESSED,
                self::CHECKOUT_REFUND_PROCESSED,
            ],
            'authentication' => [
                self::EMAIL_OTP,
                self::PASSWORD_RESET,
            ],
        ];
    }

    /**
     * Check if this purpose requires action from the user
     */
    public function requiresAction(): bool
    {
        return match($this) {
            self::USER_VERIFY_REQUEST,
            self::PROPERTY_VERIFY_REQUEST,
            self::RENT_REQUESTED,
            self::CHECKOUT_REQUESTED,
            self::CHECKOUT_AWAITING_OWNER_CONFIRMATION,
            self::CHECKOUT_REQUIRES_REVIEW,
            self::CHECKOUT_DISPUTE,
            self::PAYMENT_FAILED,
            self::EMAIL_OTP,
            self::PASSWORD_RESET => true,
            default => false,
        };
    }

    /**
     * Get the priority level of this notification purpose
     */
    public function priority(): string
    {
        return match($this) {
            self::CHECKOUT_DISPUTE,
            self::PAYMENT_FAILED,
            self::REQUEST_AUTO_CANCELLED,
            self::USER_VERIFICATION_REJECTED,
            self::PROPERTY_VERIFICATION_REJECTED => 'high',

            self::RENT_REQUESTED,
            self::CHECKOUT_REQUESTED,
            self::CHECKOUT_AWAITING_OWNER_CONFIRMATION,
            self::USER_VERIFY_REQUEST,
            self::PROPERTY_VERIFY_REQUEST,
            self::EMAIL_OTP,
            self::PASSWORD_RESET => 'medium',

            default => 'low',
        };
    }
}