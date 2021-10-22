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
                        'work_status'   => json_encode($profileObject['driver_profile']['work_status']),
                        'current_status' => $profileObject['current_status']['status'],
                        'link'          => 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'],
                ]);

                $profile->transaction()->create();
            }

            //запрос профилей
            //фильтр последняя транзация 40 дней назад
            //раскидываем по бд
        }
    }

    //выполняем работу с амо
    public function send()
    {
        $profiles = Profile::where('status', 'Добавлено')->all();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                $contact = Contacts::find($profile);

                if($contact) {

                    $leads = Leads::search($contact, $this->amocrm, env('PIPELINE'));//TODO pipeline?

                    if(count($leads) > 0) {

                        foreach ($leads as $lead) {

                            if($lead->status_id) 'sad';//TODO логика для этапов
                        }

                    } else {

                        //не найдено активных
                        //надо создать новую в УР
                    }

                } else {

                    //не найдено контакта
                    //надо создать

                }
            }
        }
    }
}
