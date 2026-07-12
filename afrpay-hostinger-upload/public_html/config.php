<?php

declare(strict_types=1);

/**
 * AfrPay connection config for Hostinger standalone API.
 */

return [
    'OR001' => [
        'name' => 'AfrPay Onramp',
        'connection_type' => 'onramp',
        'base_url' => (string) (env('AFRPAY_ONRAMP_BASE_URL') ?: env('AFRPAY_BASE_URL', '')),
        'api_key' => (string) (env('AFRPAY_ONRAMP_API_KEY') ?: env('AFRPAY_API_KEY', '')),
        'auth_scheme' => (string) (env('AFRPAY_ONRAMP_AUTH_SCHEME') ?: env('AFRPAY_AUTH_SCHEME', 'bearer')),
        'webhook_secret' => (string) (env('AFRPAY_ONRAMP_WEBHOOK_SECRET') ?: env('AFRPAY_WEBHOOK_SECRET', '')),
        'signature_header' => (string) env('AFRPAY_ONRAMP_SIGNATURE_HEADER', 'X-AfrPay-Signature'),
        'create_path' => (string) env('AFRPAY_ONRAMP_CREATE_PATH', '/v1/onramp/payments'),
        'status_path' => (string) env('AFRPAY_ONRAMP_STATUS_PATH', '/v1/onramp/payments/{reference}'),
        'refund_path' => (string) env('AFRPAY_ONRAMP_REFUND_PATH', '/v1/onramp/refunds'),
        'timeout_seconds' => (int) env('AFRPAY_TIMEOUT_SECONDS', 30),
        'force_sandbox_calls' => env_bool('AFRPAY_OR001_FORCE_SANDBOX_CALLS', false),
    ],
    'OB003' => [
        'name' => 'AfrPay Open Banking',
        'connection_type' => 'open_banking',
        'base_url' => (string) (env('AFRPAY_OPEN_BANKING_BASE_URL') ?: env('AFRPAY_BASE_URL', '')),
        'api_key' => (string) (env('AFRPAY_OPEN_BANKING_API_KEY') ?: env('AFRPAY_API_KEY', '')),
        'auth_scheme' => (string) (env('AFRPAY_OPEN_BANKING_AUTH_SCHEME') ?: env('AFRPAY_AUTH_SCHEME', 'bearer')),
        'webhook_secret' => (string) (env('AFRPAY_OPEN_BANKING_WEBHOOK_SECRET') ?: env('AFRPAY_WEBHOOK_SECRET', '')),
        'signature_header' => (string) env('AFRPAY_OPEN_BANKING_SIGNATURE_HEADER', 'X-AfrPay-Bank-Signature'),
        'create_path' => (string) env('AFRPAY_OPEN_BANKING_CREATE_PATH', '/v1/open-banking/payments'),
        'status_path' => (string) env('AFRPAY_OPEN_BANKING_STATUS_PATH', '/v1/open-banking/payments/{reference}'),
        'refund_path' => (string) env('AFRPAY_OPEN_BANKING_REFUND_PATH', '/v1/open-banking/refunds'),
        'timeout_seconds' => (int) env('AFRPAY_TIMEOUT_SECONDS', 30),
        'force_sandbox_calls' => env_bool('AFRPAY_OB003_FORCE_SANDBOX_CALLS', false),
    ],
];
