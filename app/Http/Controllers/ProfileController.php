<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function profiles()
    {
        //запрос профилей
        //фильтр последняя транзация 40 дней назад
        //раскидываем по бд
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
