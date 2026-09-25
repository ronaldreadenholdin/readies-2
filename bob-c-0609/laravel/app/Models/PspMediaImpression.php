<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspMediaImpression extends Model
{
    protected $table = 'psp_media_impressions';

    protected $fillable = [
        'impression_id',
        'media_id',
        'advertiser_id',
        'owner',
        'merchant_id',
        'slot',
        'payment_attempt_id',
        'merchant_reference',
        'shown_at',
        'visible_duration_seconds',
        'completed',
        'clicked',
        'payment_outcome',
        'variant',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'clicked' => 'boolean',
    ];
}
