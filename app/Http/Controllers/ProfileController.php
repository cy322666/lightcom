<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Profile;
use App\Models\Transaction;
use App\Services\amoCRM\Client;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use App\Services\Yandex\Auth;
use App\Services\Yandex\Services\ProfileCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

            $profilesCollection = (new ProfileCollection($auth))->index();

            foreach ($profilesCollection as $profileObject) {

                if(key_exists('last_transaction_date', (array)$profileObject['accounts'][0]) == false) {

                    $profile = Profile::where('yandex_profile_id', $profileObject['accounts'][0]['id'])->first();

                    if(!$profile) {

                        $profile = new Profile();

                        $profile->yandex_profile_id = $profileObject['accounts'][0]['id'];
                        $profile->balance        = explode( '.', $profileObject['accounts'][0]['balance'])[1];
                        $profile->first_name      = $profileObject['driver_profile']['first_name'] ?? null;
                        $profile->last_name      = $profileObject['driver_profile']['last_name'] ?? null;
                        $profile->middle_name    = $profileObject['driver_profile']['middle_name'] ?? null;
                        $profile->created_date   = $profileObject['driver_profile']['created_date'];
                        $profile->phones         = json_encode($profileObject['driver_profile']['phones']);
                        $profile->work_status    = $profileObject['driver_profile']['work_status'];
                        $profile->current_status = $profileObject['current_status']['status'];
                        $profile->link           = 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'].'/details?park_id='.$account->park_id.'&lang=ru';
                        $profile->park_id        = $account->subdomain;
                        $profile->comment        = 'Новый без активности';
                        $profile->save();

                        $profile->transaction()->create();

                        Log::info(__METHOD__.' : Новый профиль без активности с id : '.$profileObject['accounts'][0]['id']);
                    }
                }
            }

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
                $profile->link           = 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'].'/details?park_id='.$account->park_id.'&lang=ru';
                $profile->park_id        = $account->subdomain;

                //новый профиль, нет записи
                if($profile->transaction == null) { // || $profile->transaction->contact_id !== null) {
                    //пункт тз 1.1.7
                    //первый импорт профиля в систему
                    $profile->transaction()->create();

                    $profile->comment = 'Новый';

                    Log::info(__METHOD__.' : Новый профиль с id : '.$profileObject['accounts'][0]['id']);

                } elseif($profile->isDirty('last_transaction_date')) {

                    Log::info(__METHOD__.' : Обновленный профиль с id : '.$profileObject['accounts'][0]['id'].' последняя транзакция : '.$profileObject['accounts'][0]['last_transaction_date']);

                    $profile->status = 'Обновлено';
                }
                $profile->save();
            }
        }
    }

    /*
     * профили, у которых обновлено значение последней транзакции
     */
    public function send_updated()
    {
        $this->amocrm = (new Client())->init();

        $profiles = Profile::where('status', 'Обновлено')->get();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                try {

                    if($profile->transaction->contact_id) {

                        $contact = Contacts::get($this->amocrm, $profile->transaction->contact_id);

                        Contacts::update($contact, [
                            'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                            'Телефоны'  => $profile->phones,
                            'cf' => [
                                'Ссылка на профиль'         => $profile->link,
                                'Дата последней транзакции' => $profile->last_transaction_date ? date('Y-m-d', strtotime($profile->last_transaction_date)) : null,
                                //'Статус работы водителя'    => $profile->work_status,
                            ],
                        ]);

                        $profile->status  = 'OK';
                        $profile->comment = 'Обновлено';

                        Log::info(__METHOD__.' : Профиль с id : '.$profile->yandex_profile_id.' обновлен в amocrm');

                        if($profile->transaction->status !== 142) {

                            $profile->transaction->status = 'Профиль обновлен';

                            Log::info(__METHOD__.' : Профиль с id : '.$profile->yandex_profile_id.' ждет обновления транзакции');
                        } else
                            Log::info(__METHOD__.' : Профиль с id : '.$profile->yandex_profile_id.' транзакция не отслеживается');

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

        $profiles = Profile::where('status', 'Добавлено')->get();

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
                            'Дата последней транзакции' => $profile->last_transaction_date ? date('Y-m-d', strtotime($profile->last_transaction_date)) : null,
                            //'Статус работы водителя'    => $profile->work_status,
                        ],
                    ]);

                    $profile->transaction->contact_id = $contact->id;
                    $profile->transaction->save();

                    $profile->status  = 'OK';
                    $profile->comment = 'Отработан';
                    $profile->save();

                    Log::info(__METHOD__.' : Новый профиль с id : '.$profile->yandex_profile_id.' отправлен в amocrm');

                } catch (\Exception $exception) {

                    $profile->status = $exception->getMessage().' '.$exception->getFile().' '.$exception->getLine();
                    $profile->save();
                }
            }
        }
    }

    public function test()
    {
        $account = Account::where('park_id', '7c76fa6276ad4c329becde8053311597')->first();

        $auth = (new Auth())->setAccess($account);

        $profilesCollection = (new ProfileCollection($auth))->index();

        foreach ($profilesCollection as $profileObject) {

           // print_r($profileObject);echo "\n";exit;
            if($profileObject['driver_profile']['id'] == '9768180c9255a751968e72387faca68b') {

                print_r($profileObject);exit;
            }
        }
    }
}
