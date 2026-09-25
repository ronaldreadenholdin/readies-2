<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspTestIsolationEvent extends Model
{
    protected $table = 'psp_test_isolation_events';

    protected $fillable = [
        'event_type',
        'connection_code',
        'merchant_id',
        'test_site',
        'actor',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
