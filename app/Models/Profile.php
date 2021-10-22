<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_transaction_date',
        'yandex_profile_id',
        'balance',
        'first_name',
        'last_name',
        'middle_name',
        'created_date',
        'phones',
        'work_status',
        'current_status',
        'link',
        'park_id',
    ];

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
