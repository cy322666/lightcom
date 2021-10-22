<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_transaction_date',
        'balance',
        'first_name',
        'last_name',
        'middle_name',
        'created_date',
        'phones',
        'work_status',
        'current_status',
        'link',
    ];

    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }
}
