<?php

declare(strict_types=1);

namespace AfrPay\Services;

final class GoLiveGate
{
    public static function status(): array
    {
        return [
            'live_enabled' => env_bool('AFRPAY_LIVE_ENABLED', false),
            'test_approved' => env_bool('AFRPAY_TEST_APPROVED', false),
            'or001_approved' => env_bool('AFRPAY_OR001_TEST_APPROVED', env_bool('AFRPAY_TEST_APPROVED', false)),
            'ob003_approved' => env_bool('AFRPAY_OB003_TEST_APPROVED', env_bool('AFRPAY_TEST_APPROVED', false)),
            'approved_by' => env('AFRPAY_TEST_APPROVED_BY'),
            'approved_at' => env('AFRPAY_TEST_APPROVED_AT'),
        ];
    }

    public static function assertMayCall(string $code, array $config): void
    {
        if (! empty($config['force_sandbox_calls'])) {
            return;
        }

        if (! env_bool('AFRPAY_LIVE_ENABLED', false)) {
            throw new \RuntimeException(
                'AfrPay live API is locked. Set AFRPAY_LIVE_ENABLED=true only after pre-flight test approval.'
            );
        }

        $key = 'AFRPAY_'.$code.'_TEST_APPROVED';
        $approved = env_bool($key, env_bool('AFRPAY_TEST_APPROVED', false));
        if (! $approved) {
            throw new \RuntimeException(
                "AfrPay connection {$code} is not test-approved yet. Set {$key}=true after green pre-flight."
            );
        }

        if (($config['base_url'] ?? '') === '' || ($config['api_key'] ?? '') === '') {
            throw new \RuntimeException("AfrPay {$code} credentials incomplete: base_url and api_key required.");
        }
    }
}
