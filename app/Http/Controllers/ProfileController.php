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

            $profilesCollection = (new ProfileCollection($auth))->all();

            foreach ($profilesCollection as $profileObject) {

                $profile = Profile::where('yandex_profile_id', $profileObject['accounts'][0]['id'])->first();

                if(!$profile) {

                    $profile = new Profile();

                    $profile->yandex_profile_id     = $profileObject['accounts'][0]['id'];
                    $profile->last_transaction_date = $profileObject['accounts'][0]['last_transaction_date'] ?? null;
                    $profile->balance        = explode( '.', $profileObject['accounts'][0]['balance'])[1];
                    $profile->first_name     = $profileObject['driver_profile']['first_name'] ?? null;
                    $profile->last_name      = $profileObject['driver_profile']['last_name'] ?? null;
                    $profile->middle_name    = $profileObject['driver_profile']['middle_name'] ?? null;
                    $profile->created_date   = $profileObject['driver_profile']['created_date'];
                    $profile->phones         = json_encode($profileObject['driver_profile']['phones']);
                    $profile->work_status    = $profileObject['driver_profile']['work_status'];
                    $profile->current_status = $profileObject['current_status']['status'];
                    $profile->link           = 'https://fleet.yandex.ru/drivers/'.$profileObject['accounts'][0]['id'].'/details?park_id='.$account->park_id.'&lang=ru';
                    $profile->park_id        = $account->subdomain;
                    $profile->comment        = 'Новый импорт';
                    $profile->save();

                    $transaction = $profile->transaction()->create();

                    $profile->transaction_id = $transaction->id;

                } elseif(key_exists('last_transaction_date', $profileObject['accounts'][0]) && $profile->last_transaction_date != $profileObject['accounts'][0]['last_transaction_date']) {

                    $profile->last_transaction_date = $profileObject['accounts'][0]['last_transaction_date'];
                    $profile->balance  = explode( '.', $profileObject['accounts'][0]['balance'])[1];
                    $profile->status   = 'Обновлен';
                    $profile->comment  = 'Обновлен существующий';

                    $profile->transaction->status  = 'Профиль обновлен';
                }

                $profile->push();
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

        $profiles = Profile::where('status', '!=', 'Не отслеживается')
            ->where('contact_id', null)
            ->get();

        if($profiles->count() > 0) {

            foreach ($profiles as $profile) {

                try {
                    $contact = Contacts::search(['Телефоны'  => $profile->phones], $this->amocrm);

                    if($contact) {

                        $profile->transaction->status  = 'Найден контакт';
                        $profile->transaction->comment = 'Проверить сделки';

                        $profile->status  = 'Отслеживается';

                    } else {

                        $contact = Contacts::create($this->amocrm, $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name);

                        $profile->transaction->status  = 'Новый контакт';
                        $profile->transaction->comment = 'Создать сделку в УР';

                        $profile->status = 'Не отслеживается';
                    }

                    $contact = Contacts::update($contact, [
                        'Имя'       => $profile->first_name.' '.$profile->last_name.' '.$profile->middle_name,
                        'Телефоны'  => $profile->phones,
                        'cf' => [
                            'Ссылка на профиль'         => $profile->link,
                            'Дата создания профиля'     => date('Y-m-d', strtotime($profile->created_date)),
                            'Дата последней транзакции' => $profile->last_transaction_date ? date('Y-m-d', strtotime($profile->last_transaction_date)) : null,
                        ],
                    ]);

                    $profile->contact_id = $contact->id;
                    $profile->push();

                } catch (\Exception $exception) {

                    $profile->status = $exception->getMessage().' '.$exception->getFile().' '.$exception->getLine();
                    $profile->push();
                }
            }
        }
    }

    public function test()
    {
        $profiles = Transaction::where('comment', 'Создать сделку в УР')->get();

        foreach ($profiles as $profile) {

            $profile->profile->status = 'Не отслеживается';
            $profile->profile->save();
        }
    }
}
