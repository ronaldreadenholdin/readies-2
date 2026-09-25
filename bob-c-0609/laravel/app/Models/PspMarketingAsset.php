<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspMarketingAsset extends Model
{
    protected $table = 'psp_marketing_assets';

    protected $fillable = [
        'asset_id',
        'title',
        'type',
        'explanation',
        'file_url',
        'storage_path',
        'external_url',
        'thumbnail_url',
        'allowed_slots',
        'owner',
        'advertiser_id',
        'fee_rate',
        'status',
        'starts_at',
        'ends_at',
        'upload_mime_type',
        'file_size',
        'checksum',
    ];

    protected $casts = [
        'allowed_slots' => 'array',
        'fee_rate' => 'array',
    ];
}
