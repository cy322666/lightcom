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

    public static function getLastDays(string $lastDays) : int
    {
        $last_date = date('Y-m-d', strtotime($lastDays));

        $seconds = abs(strtotime(date('Y-m-d') - $last_date);

        return round(floor($seconds / 86400));
    }

    public function getStatusLastDays(int $lastDays, int $createdDays) : ?int
    {
        if ($lastDays > 15) {


        } elseif($lastDays >= 2 && $createdDays == 2) {


        } elseif($lastDays <= 15 && $lastDays > 5) {


        } else
            dd('не подошло под условие');
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
