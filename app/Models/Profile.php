<?php

namespace App\Models;

use Exception;
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
        'contact_id',
        'park_id',
    ];

    /**
     * @throws Exception
     */
    public static function getLastDays(string $lastDaysDate) : int
    {
        $lastDays = new \DateTime(date('Y-m-d', strtotime($lastDaysDate)));

        return (new \DateTime)->diff($lastDays)->days;
    }

    /**
     * @throws Exception
     */
    public static function getLastDaysTimestamp(string $timestamp) : int
    {
        $lastDate = (new \DateTime)->setTimestamp($timestamp);

        return (new \DateTime)->diff($lastDate)->days;
    }

    public static function getStatusLastDays(int $lastDays, int $createdDays) : ?int
    {
        if ($lastDays > 15) {

            $status_id = env('AMO_STATUS_ID_15_DAYS');

        } elseif($createdDays >= 2 && $lastDays == 2) {

            $status_id = env('AMO_STATUS_ID_2_DAYS');

        } elseif($lastDays < 14 && $lastDays > 5) {

            $status_id = env('AMO_STATUS_ID_5_DAYS');

        } else
            $status_id = null;

        return $status_id;
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
