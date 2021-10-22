<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Profile;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use App\Services\Yandex\Auth;
use App\Services\Yandex\Services\ProfileCollection;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * получает со всех аккаунтов профили водителей
     * из Яндекс с активностью не позже 40 дней
     */
    public function profiles()
    {
        $accounts = Account::where('service', 'yandex drive')->get();

        foreach ($accounts as $account) {

            $auth = (new Auth())->setAccess($account);

            $profilesCollection = (new ProfileCollection($auth))->all();

            foreach ($profilesCollection as $profileObject) {

                $profile = Profile::updateOrCreate(['yandex_profile_id' => $profileObject['accounts'][0]['id']], [

                        'last_transaction_date' => $profileObject['accounts'][0]['last_transaction_date'],
                        'balance'       => explode( '.', $profileObject['accounts'][0]['balance'])[1],
                        'first_name'     => $profileObject['driver_profile']['first_name'],
                        'last_name'     => $profileObject['driver_profile']['last_name'],
                        'middle_name'   => $profileObject['driver_profile']['middle_name'],
                        'created_date'  => $profileObject['driver_profile']['created_date'],
                        'phones'        => json_encode($profileObject['driver_profile']['phones']),
                        'work_status'   => $profileObject['driver_profile']['work_status'],
                        'current_status'=> $profileObject['current_status']['status'],
                        'link'          => 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'],
                        'park_id'       => $account->subdomain,
                ]);

                $transaction = $profile->transaction()->create();

                $profile->transaction_id = $transaction->id;
                $profile->save();
            }
        }
    }

    /**
     *  проверяет наличие контакта в амо или создает новый
     *  записывает contact_id в транзакцию
     *  подготовка перед отправкой лидов(транзакции) в амо
     */
    public function send()
    {
        $profiles = Profile::where('status', 'Добавлено')->all();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                $contact = Contacts::search($profile, $this->amocrm);//TODO add logic

                if($contact) {

                    $contact = Contacts::update($contact, [//TODO add logic
                        'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                        'Телефоны'  => $profile->phones,
                        'Ссылка'    => $profile->link,
                        //дата создания
                    ]);

                    $profile->transaction->status = 'Найден контакт';

                } else {

                    $contact = Contacts::create($this->amocrm, [//TODO add logic
                        'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                        'Телефоны'  => $profile->phones,
                        'Ссылка'    => $profile->link,
                        //дата создания
                    ]);

                    $profile->transaction->status = 'Новый контакт';
                }

                $profile->transaction->contact_id = $contact->id;
                $profile->transaction->save();

                $profile->status = 'OK';
                $profile->save();
            }
        }
    }
}
