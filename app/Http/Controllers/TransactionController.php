<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Transaction;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     *  обрабатывает ожидающие транзакции
     *  создает/обновляет сделки по заложенной логике
     */
    public function send()
    {
        //выполняем работу с амо
        $transactions = Transaction::where('status', 'Добавлено');//TODO

        if($transactions->count() > 0) {

            foreach ($transactions as $transaction) {

                if($transaction->profile->status == 'Новый контакт') {

                    //создаем в УР
                } else {

                    //контакт уже был, основная логика

                    $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                    $leads = Leads::search($contact, $this->amocrm, env('AMO_PIPELINE_ID'));

                    if($leads !== null) {

                        //есть активные сделка/ки у контакта

                    } else {

                        //нет сделок, надо создать новую
                    }

                    //TODO по ласт апдейт узнать сколько прошло и получить ид этапа
                    //TODO переместить создать сделку в нем
                    //TODO логирование действий!
                }
            }
        }
    }
}
