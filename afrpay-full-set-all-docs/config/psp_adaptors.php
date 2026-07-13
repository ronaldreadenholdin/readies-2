<?php

use App\Services\Psp\Adaptors\AfrPayOnrampAdaptor;
use App\Services\Psp\Adaptors\AfrPayOpenBankingAdaptor;

/**
 * AfrPay live connections.
 * Paths are configurable — override via env if AfrPay docs use different routes.
 */
return [
    'connections' => [
        'OR001' => [
            'name' => 'AfrPay Onramp',
            'connection_type' => 'onramp',
            'class' => AfrPayOnrampAdaptor::class,
            'base_url' => env('AFRPAY_ONRAMP_BASE_URL', env('AFRPAY_BASE_URL', '')),
            'api_key' => env('AFRPAY_ONRAMP_API_KEY', env('AFRPAY_API_KEY', '')),
            'auth_scheme' => env('AFRPAY_ONRAMP_AUTH_SCHEME', env('AFRPAY_AUTH_SCHEME', 'bearer')),
            'webhook_secret' => env('AFRPAY_ONRAMP_WEBHOOK_SECRET', env('AFRPAY_WEBHOOK_SECRET', '')),
            'signature_header' => env('AFRPAY_ONRAMP_SIGNATURE_HEADER', 'X-AfrPay-Signature'),
            'create_path' => env('AFRPAY_ONRAMP_CREATE_PATH', '/v1/onramp/payments'),
            'status_path' => env('AFRPAY_ONRAMP_STATUS_PATH', '/v1/onramp/payments/{reference}'),
            'refund_path' => env('AFRPAY_ONRAMP_REFUND_PATH', '/v1/onramp/refunds'),
            'timeout_seconds' => (int) env('AFRPAY_TIMEOUT_SECONDS', 30),
            'replay_tolerance_seconds' => (int) env('AFRPAY_REPLAY_TOLERANCE_SECONDS', 300),
            // Set true only for dry-run sandbox wiring before formal go-live unlock.
            'force_sandbox_calls' => filter_var(env('AFRPAY_OR001_FORCE_SANDBOX_CALLS', false), FILTER_VALIDATE_BOOLEAN),
            'status_map' => [
                // Add AfrPay-specific statuses here after docs confirm names.
            ],
        ],

        'OB003' => [
            'name' => 'AfrPay Open Banking',
            'connection_type' => 'open_banking',
            'class' => AfrPayOpenBankingAdaptor::class,
            'base_url' => env('AFRPAY_OPEN_BANKING_BASE_URL', env('AFRPAY_BASE_URL', '')),
            'api_key' => env('AFRPAY_OPEN_BANKING_API_KEY', env('AFRPAY_API_KEY', '')),
            'auth_scheme' => env('AFRPAY_OPEN_BANKING_AUTH_SCHEME', env('AFRPAY_AUTH_SCHEME', 'bearer')),
            'webhook_secret' => env('AFRPAY_OPEN_BANKING_WEBHOOK_SECRET', env('AFRPAY_WEBHOOK_SECRET', '')),
            'signature_header' => env('AFRPAY_OPEN_BANKING_SIGNATURE_HEADER', 'X-AfrPay-Bank-Signature'),
            'create_path' => env('AFRPAY_OPEN_BANKING_CREATE_PATH', '/v1/open-banking/payments'),
            'status_path' => env('AFRPAY_OPEN_BANKING_STATUS_PATH', '/v1/open-banking/payments/{reference}'),
            'refund_path' => env('AFRPAY_OPEN_BANKING_REFUND_PATH', '/v1/open-banking/refunds'),
            'timeout_seconds' => (int) env('AFRPAY_TIMEOUT_SECONDS', 30),
            'replay_tolerance_seconds' => (int) env('AFRPAY_REPLAY_TOLERANCE_SECONDS', 300),
            'force_sandbox_calls' => filter_var(env('AFRPAY_OB003_FORCE_SANDBOX_CALLS', false), FILTER_VALIDATE_BOOLEAN),
            'status_map' => [],
        ],
    ],
];
