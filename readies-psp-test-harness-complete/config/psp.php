<?php

return [
    'fbls' => [
        'code' => 'P003',
        'name' => 'FBLS',
        'region' => 'global',
        'webhook_secret' => env('FBLS_WEBHOOK_SECRET'),
        'signature_header' => 'X-FBLS-Signature',
        'signature_method' => 'hmac-sha256',
        'replay_tolerance_seconds' => 300,
    ],

    'xcore' => [
        'code' => 'P004',
        'name' => 'Xcore',
        'region' => 'europe',
        'webhook_secret' => env('XCORE_WEBHOOK_SECRET'),
        'signature_header' => 'X-XCore-Signature',
        'signature_method' => 'hmac-sha256',
        'replay_tolerance_seconds' => 300,
        'requires_3ds' => true,
        'name_rules' => [
            'latin_letters_only' => true,
            'minimum_length' => 2,
            'email_not_allowed' => true,
        ],
    ],
];
