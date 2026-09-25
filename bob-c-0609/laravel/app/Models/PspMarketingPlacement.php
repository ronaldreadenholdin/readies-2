<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspMarketingPlacement extends Model
{
    protected $table = 'psp_marketing_placements';

    protected $fillable = [
        'placement_id',
        'asset_id',
        'slot',
        'merchant_id',
        'site',
        'page_or_flow_step',
        'active_from',
        'active_to',
        'switched_on_by',
    ];
}
