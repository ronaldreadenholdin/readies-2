<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedCustomer extends Model
{
    protected $table = 'trusted_customers';

    protected $fillable = [
        'merchant',
        'email',
        'phone',
        'card_first6_last4',
        'birthday',
        'full_name',
        'biz',
        'successful_payments',
        'last_provider',
        'last_paid_at',
    ];

    protected $casts = [
        'last_paid_at' => 'datetime',
        'birthday' => 'date',
    ];
}
