<?php

use App\Services\Psp\Adaptors\AfrPayOnrampAdaptor;
use App\Services\Psp\Adaptors\AfrPayOpenBankingAdaptor;

return [
    'connections' => [
        'OR001' => [
            'name' => 'AfrPay Onramp',
            'connection_type' => 'onramp',
            'class' => AfrPayOnrampAdaptor::class,
            'base_url' => env('AFRPAY_ONRAMP_BASE_URL', ''),
            'api_key' => env('AFRPAY_ONRAMP_API_KEY', ''),
            'webhook_secret' => env('AFRPAY_ONRAMP_WEBHOOK_SECRET', ''),
            'signature_header' => env('AFRPAY_ONRAMP_SIGNATURE_HEADER', 'X-AfrPay-Signature'),
            'create_path' => env('AFRPAY_ONRAMP_CREATE_PATH', '/payments'),
            'status_path' => env('AFRPAY_ONRAMP_STATUS_PATH', '/payments/{reference}'),
            'refund_path' => env('AFRPAY_ONRAMP_REFUND_PATH', '/refunds'),
        ],

        'OB003' => [
            'name' => 'AfrPay Open Banking',
            'connection_type' => 'open_banking',
            'class' => AfrPayOpenBankingAdaptor::class,
            'base_url' => env('AFRPAY_OPEN_BANKING_BASE_URL', ''),
            'api_key' => env('AFRPAY_OPEN_BANKING_API_KEY', ''),
            'webhook_secret' => env('AFRPAY_OPEN_BANKING_WEBHOOK_SECRET', ''),
            'signature_header' => env('AFRPAY_OPEN_BANKING_SIGNATURE_HEADER', 'X-AfrPay-Bank-Signature'),
            'create_path' => env('AFRPAY_OPEN_BANKING_CREATE_PATH', '/open-banking/payments'),
            'status_path' => env('AFRPAY_OPEN_BANKING_STATUS_PATH', '/open-banking/payments/{reference}'),
            'refund_path' => env('AFRPAY_OPEN_BANKING_REFUND_PATH', '/open-banking/refunds'),
        ],
    ],
];
