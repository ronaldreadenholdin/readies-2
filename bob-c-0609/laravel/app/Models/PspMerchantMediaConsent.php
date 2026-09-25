<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspMerchantMediaConsent extends Model
{
    protected $table = 'psp_merchant_media_consent';

    protected $fillable = [
        'merchant_id',
        'media_id',
        'slot',
        'approved',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved' => 'boolean',
    ];
}
