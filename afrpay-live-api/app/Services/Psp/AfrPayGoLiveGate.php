<?php

namespace App\Services\Psp;

use RuntimeException;

/**
 * Blocks live AfrPay provider calls until pre-flight tests are approved
 * and AFRPAY_LIVE_ENABLED is turned on.
 */
class AfrPayGoLiveGate
{
    public static function assertConnectionMayCallProvider(string $code, array $connectionConfig): void
    {
        $liveEnabled = filter_var(env('AFRPAY_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $approved = filter_var(env('AFRPAY_TEST_APPROVED', false), FILTER_VALIDATE_BOOLEAN);
        $connectionApprovedKey = 'AFRPAY_'.$code.'_TEST_APPROVED';
        $connectionApproved = filter_var(env($connectionApprovedKey, $approved), FILTER_VALIDATE_BOOLEAN);

        if (! empty($connectionConfig['force_sandbox_calls'])) {
            return;
        }

        if (! $liveEnabled) {
            throw new RuntimeException(
                'AfrPay live API is locked. Set AFRPAY_LIVE_ENABLED=true only after pre-flight test approval.'
            );
        }

        if (! $connectionApproved) {
            throw new RuntimeException(sprintf(
                'AfrPay connection %s is not test-approved yet. Set %s=true (or AFRPAY_TEST_APPROVED=true) after green pre-flight.',
                $code,
                $connectionApprovedKey
            ));
        }

        if (empty($connectionConfig['base_url']) || empty($connectionConfig['api_key'])) {
            throw new RuntimeException(sprintf(
                'AfrPay %s credentials incomplete: base_url and api_key are required for live calls.',
                $code
            ));
        }
    }

    public static function status(): array
    {
        return [
            'live_enabled' => filter_var(env('AFRPAY_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'test_approved' => filter_var(env('AFRPAY_TEST_APPROVED', false), FILTER_VALIDATE_BOOLEAN),
            'or001_approved' => filter_var(env('AFRPAY_OR001_TEST_APPROVED', env('AFRPAY_TEST_APPROVED', false)), FILTER_VALIDATE_BOOLEAN),
            'ob003_approved' => filter_var(env('AFRPAY_OB003_TEST_APPROVED', env('AFRPAY_TEST_APPROVED', false)), FILTER_VALIDATE_BOOLEAN),
            'approved_by' => env('AFRPAY_TEST_APPROVED_BY'),
            'approved_at' => env('AFRPAY_TEST_APPROVED_AT'),
        ];
    }
}
