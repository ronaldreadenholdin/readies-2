<?php

namespace App\Services\Psp\Contracts\V2;

final class CanonicalStatus
{
    public const PENDING = 'pending';
    public const AUTHORIZED = 'authorized';
    public const CAPTURED = 'captured';
    public const SETTLED = 'settled';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const SKIPPED = 'skipped';
    public const ERROR = 'error';

    public const ALL = [
        self::PENDING,
        self::AUTHORIZED,
        self::CAPTURED,
        self::SETTLED,
        self::DECLINED,
        self::CANCELLED,
        self::REFUNDED,
        self::SKIPPED,
        self::ERROR,
    ];

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}

final class DeclineClass
{
    public const NONE = 'none';
    public const SOFT = 'soft';
    public const HARD = 'hard';

    public const ALL = [
        self::NONE,
        self::SOFT,
        self::HARD,
    ];

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}

final class ErrorReason
{
    public const AMOUNT_MISMATCH = 'amount_mismatch';
    public const TRANSPORT_ERROR_BEFORE_ACCEPTANCE = 'transport_error_before_acceptance';
    public const MISSING_REDIRECT = 'missing_redirect';
    public const PSP_CONFIG_ERROR = 'psp_config_error';

    public const ALL = [
        self::AMOUNT_MISMATCH,
        self::TRANSPORT_ERROR_BEFORE_ACCEPTANCE,
        self::MISSING_REDIRECT,
        self::PSP_CONFIG_ERROR,
    ];

    public static function isValid(?string $value): bool
    {
        return $value === null || in_array($value, self::ALL, true);
    }
}

final class NextAction
{
    public const NONE = 'none';
    public const REDIRECT = 'redirect';
    public const POLL = 'poll';
    public const CASCADE = 'cascade';

    public const ALL = [
        self::NONE,
        self::REDIRECT,
        self::POLL,
        self::CASCADE,
    ];

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}

final class PaymentEventSource
{
    public const WEBHOOK = 'webhook';
    public const RETURN = 'return';
    public const POLL = 'poll';
    public const REBOOK = 'rebook';

    public const ALL = [
        self::WEBHOOK,
        self::RETURN,
        self::POLL,
        self::REBOOK,
    ];

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}

final class RedirectMethod
{
    public const GET = 'GET';
    public const POST = 'POST';

    public const ALL = [
        self::GET,
        self::POST,
    ];

    public static function isValid(?string $value): bool
    {
        return $value === null || in_array($value, self::ALL, true);
    }
}

final class DisplayStatus
{
    public const APPROVED = 'approved';
    public const PENDING = 'pending';
    public const THREE_DS_REDIRECT = '3ds_redirect';
    public const DECLINED_SOFT = 'declined_soft';
    public const DECLINED_HARD = 'declined_hard';
    public const ERROR = 'error';

    public const ALL = [
        self::APPROVED,
        self::PENDING,
        self::THREE_DS_REDIRECT,
        self::DECLINED_SOFT,
        self::DECLINED_HARD,
        self::ERROR,
    ];

    public static function isValid(?string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}
