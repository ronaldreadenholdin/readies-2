<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspProvider extends Model
{
    protected $table = 'psp_providers';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'is_enabled' => 'boolean',
        'active' => 'boolean',
        'is_active' => 'boolean',
        'live' => 'boolean',
        'credentials' => 'array',
        'settings' => 'array',
        'config' => 'array',
        'metadata' => 'array',
        'supported_currencies' => 'array',
        'restricted_countries' => 'array',
        'blocked_countries' => 'array',
        'webhook_events' => 'array',
    ];
}
