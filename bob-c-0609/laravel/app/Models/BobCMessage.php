<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BobCMessage extends Model
{
    protected $table = 'bob_c_messages';

    protected $fillable = [
        'user_id',
        'role',
        'content',
    ];
}
