<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspMediaFeeRate extends Model
{
    protected $table = 'psp_media_fee_rates';

    protected $fillable = [
        'media_id',
        'advertiser_id',
        'billable_event',
        'currency',
        'amount',
        'merchant_revenue_share_percent',
        'starts_at',
        'ends_at',
    ];
}
