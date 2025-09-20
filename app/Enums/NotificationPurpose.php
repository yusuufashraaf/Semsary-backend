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
}