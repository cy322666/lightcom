<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Profile;
use App\Services\amoCRM\Client;
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

                $profile = Profile::where('yandex_profile_id', $profileObject['accounts'][0]['id'])->first();

                if(!$profile)
                    $profile = new Profile();

                $profile->yandex_profile_id = $profileObject['accounts'][0]['id'];
                $profile->last_transaction_date = $profileObject['accounts'][0]['last_transaction_date'];
                $profile->balance        = explode( '.', $profileObject['accounts'][0]['balance'])[1];
                $profile->first_name      = $profileObject['driver_profile']['first_name'];
                $profile->last_name      = $profileObject['driver_profile']['last_name'];
                $profile->middle_name    = $profileObject['driver_profile']['middle_name'];
                $profile->created_date   = $profileObject['driver_profile']['created_date'];
                $profile->phones         = json_encode($profileObject['driver_profile']['phones']);
                $profile->work_status    = $profileObject['driver_profile']['work_status'];
                $profile->current_status = $profileObject['current_status']['status'];
                $profile->link           = 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'];
                $profile->park_id        = $account->subdomain;
                $profile->save();

                //новый профиль, нет записи
                if($profile->transaction == null) { // || $profile->transaction->contact_id !== null) {
                    //пункт тз 1.1.7
                    //первый импорт профиля в систему
                    $profile->transaction()->create();

                    $profile->comment = 'Новый импортированный';
                    $profile->save();

                } elseif($profile->isDirty('last_transaction_date')) {

                    $profile->status = 'Обновлено';
                    $profile->save();
                }
            }
        }
    }

    /*
     * профили, у которых обновлено значение последней транзакции
     */
    public function send_updated()
    {
        $this->amocrm = (new Client())->init();

        $profiles = Profile::where('status', 'Обновлено')->limit(20)->get();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                try {

                    if($profile->transaction->contact_id) {

                        $contact = Contacts::get($this->amocrm, $profile->transaction->contact_id);

                        Contacts::update($contact, [
                            'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                            'Телефоны'  => $profile->phones,
                            'cf' => [
                                'Дата последней транзакции' => date('Y-m-d', strtotime($profile->last_transaction_date)),
                                'Статус работы водителя'    => $profile->work_status,
                            ],
                        ]);

                        $profile->status  = 'OK';
                        $profile->comment = 'Обновлено';

                        $profile->transaction->status = 'Профиль обновлен';
                        $profile->push();
                    }
                } catch (\Exception $exception) {

                    $profile->status = $exception->getMessage();

                    $profile->transaction->status = 'Ошибка при обработке профиля';

                    $profile->push();
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
        $this->amocrm = (new Client())->init();

        $profiles = Profile::where('status', 'Добавлено')->limit(20)->get();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                try {
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
                    $profile->comment = 'Отработан';
                    $profile->save();

                } catch (\Exception $exception) {

                    $profile->status = $exception->getMessage();
                    $profile->save();
                }
            }
        }
    }
}
