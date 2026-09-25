<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PspWaitingMedia extends Model
{
    protected $table = 'psp_waiting_media';

    protected $fillable = [
        'media_id',
        'title',
        'type',
        'file_url',
        'storage_path',
        'thumbnail_url',
        'duration_seconds',
        'format',
        'file_size',
        'owner',
        'rights_or_licence',
        'status',
        'approved_by',
        'approved_at',
        'checksum',
    ];
}
