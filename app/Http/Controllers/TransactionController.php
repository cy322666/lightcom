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

                $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                if($transaction->profile->status == 'Новый контакт') {

                    //создаем в УР
                    $lead = Leads::create($contact, [
                        'sale'      => $transaction->profile->balance,
                        'status_id' => 142,
                        'pipeline_id' => env('AMO_PIPELINE_ID'),
                    ], 'Новая сделка интеграция с Яндекс');

                    $transaction->lead_id = $lead->id;
                    $transaction->status_id = $lead->status_id;
                    $transaction->status  = 'Нет активных | нет закрытых';
                    $transaction->save();

                } else {

                    //контакт уже был, основная логика

                    $leads = Leads::search($contact, $this->amocrm, env('AMO_PIPELINE_ID'));

                    //сколько дней прошло с транзакции
                    $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                    //сколько дней прошло с создания
                    $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                    $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                    if($leads !== null) {

                        //есть активные сделка/ки у контакта
                        //нужно узнать на каком этапе и выполнить действие
                        //TODO нюанс в проверке на перемещение назад

                        $lead = $leads->first();

                        $lead->status_id = $status_id;
                        $lead->sale = $transaction->profile->balance;
                        $lead->save();

                        $transaction->lead_id = $lead->id;
                        $transaction->status_id = $lead->status_id;
                        $transaction->status  = 'Нет активных | нет закрытых';
                        $transaction->save();

                    } else {

                        //проверка 143 этапа (5 дней)

                        $leads = Leads::searchByStatus($contact, env('AMO_PIPELINE_ID'), $this->amocrm, 143);

                        if(count($leads) > 0) {

                            foreach ($leads as $lead) {

                                if(strtotime($lead->updated_at) < 5) {//TODO 5 days

                                    //редачилась < 5 дней назад
                                    //log
                                    $transaction->status = 'Нет активных | < 5 дней';
                                    $transaction->save();

                                    continue 2;
                                }
                            }
                            //тут нужно, но и так ок
                        }
                        //нет сделок, надо создать новую
                        $lead = Leads::create($contact, [
                            'sale'      => $transaction->profile->balance,
                            'status_id' => $status_id,
                        ], 'Новая сделка интеграция с Яндекс');

                        $transaction->lead_id   = $lead->id;
                        $transaction->status_id = $lead->status_id;
                        $transaction->status    = 'Нет активных | нет закрытых';
                        $transaction->save();
                    }

                    //TODO логирование действий!
                }
            }
        }
    }
}
