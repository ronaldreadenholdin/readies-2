<?php

return [
    'enabled' => true,
    'require_auth' => (bool) env('BOB_C_REQUIRE_AUTH', false),
    'xai_api_key' => env('XAI_API_KEY'),
    'xai_model' => env('XAI_MODEL', 'grok-3'),
    'xai_base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
];
