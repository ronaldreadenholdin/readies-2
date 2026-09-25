<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPspOverride extends Model
{
    protected $table = 'merchant_psp_overrides';

    protected $fillable = [
        'merchant_id',
        'connection_code',
        'from_position',
        'to_position',
        'reason',
        'overridden_by',
        'overridden_at',
    ];
}
