<?php

use App\Services\Psp\Adaptors\CashForoOnrampAdaptor;
use App\Services\Psp\Adaptors\CashForoOpenBankingAdaptor;

return [
    'connections' => [
        'OR001' => [
            'name' => 'CashForo Onramp',
            'connection_type' => 'onramp',
            'class' => CashForoOnrampAdaptor::class,
            'base_url' => env('CASHFORO_ONRAMP_BASE_URL', ''),
            'api_key' => env('CASHFORO_ONRAMP_API_KEY', ''),
            'webhook_secret' => env('CASHFORO_ONRAMP_WEBHOOK_SECRET', ''),
            'signature_header' => env('CASHFORO_ONRAMP_SIGNATURE_HEADER', 'X-CashForo-Signature'),
            'create_path' => env('CASHFORO_ONRAMP_CREATE_PATH', '/payments'),
            'status_path' => env('CASHFORO_ONRAMP_STATUS_PATH', '/payments/{reference}'),
            'refund_path' => env('CASHFORO_ONRAMP_REFUND_PATH', '/refunds'),
        ],

        'OB003' => [
            'name' => 'CashForo Open Banking',
            'connection_type' => 'open_banking',
            'class' => CashForoOpenBankingAdaptor::class,
            'base_url' => env('CASHFORO_OPEN_BANKING_BASE_URL', ''),
            'api_key' => env('CASHFORO_OPEN_BANKING_API_KEY', ''),
            'webhook_secret' => env('CASHFORO_OPEN_BANKING_WEBHOOK_SECRET', ''),
            'signature_header' => env('CASHFORO_OPEN_BANKING_SIGNATURE_HEADER', 'X-CashForo-Bank-Signature'),
            'create_path' => env('CASHFORO_OPEN_BANKING_CREATE_PATH', '/open-banking/payments'),
            'status_path' => env('CASHFORO_OPEN_BANKING_STATUS_PATH', '/open-banking/payments/{reference}'),
            'refund_path' => env('CASHFORO_OPEN_BANKING_REFUND_PATH', '/open-banking/refunds'),
        ],
    ],
];
