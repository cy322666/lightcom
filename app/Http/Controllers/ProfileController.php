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
     * из Яндекс с активностью не позже 39 дней
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

                if($profile->transaction == null || $profile->transaction->contact_id !== null) {
                    //пункт тз 1.1.7
                    //первый импорт профиля в систему
                    $transaction = $profile->transaction()->create();

                    $profile->comment = 'Новый импортированный';
                    $profile->transaction_id = $transaction->id;
                    $profile->save();

                } else {
                    //изменился уже импортированный профиль
                    if($profile->isDirty()) {

                        $contact = Contacts::get($this->amocrm, $profile->transaction->contact_id);

                        //предполагается, что contact_id у таких записей известен
                        Contacts::update($contact, [
                            'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                            'Телефоны'  => $profile->phones,
                            'cf' => [
                                'Дата последней транзакции' => date('Y-m-d', strtotime($profile->last_transaction_date)),
                                'Статус работы водителя'    => $profile->work_status,
                            ],
                        ]);

                        $profile->comment = 'Изменился ранее импортированный';
                        $profile->save();

                        $profile->transaction->status  = 'Добавлено';
                        $profile->transaction->comment = 'Правки по импортированному профилю';
                        $profile->transaction->save();

                        //TODO изменился профиль, надо изменить и сделку | пока что сделал повторную отработку
                    }
                }
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

                $contact = Contacts::search(['Телефоны'  => $profile->phones], $this->amocrm);

                if($contact) {

                    $profile->transaction->status  = 'Найден контакт';
                    $profile->transaction->comment = 'Проверить сделки';

                } else {

                    $contact = Contacts::create($this->amocrm, $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name);

                    $profile->transaction->status  = 'Новый контакт';
                    $profile->transaction->comment = 'Создать сделку в УР';
                }

                $contact = Contacts::update($contact, [
                    'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                    'Телефоны'  => $profile->phones,
                    'cf' => [
                        'Ссылка на профиль'         => $profile->link,
                        'Дата создания профиля'     => date('Y-m-d', strtotime($profile->created_date)),
                        'Дата последней транзакции' => date('Y-m-d', strtotime($profile->last_transaction_date)),
                        'Статус работы водителя'    => $profile->work_status,
                    ],
                ]);

                $profile->transaction->contact_id = $contact->id;
                $profile->transaction->save();

                $profile->status  = 'OK';
                $profile->comment = 'Отработан как импортированный';
                $profile->save();
            }
        }
    }
}
