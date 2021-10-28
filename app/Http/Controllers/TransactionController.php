<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Transaction;
use App\Services\amoCRM\Client;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     *  обрабатывает ожидающие транзакции
     *  создает/обновляет сделки по заложенной логике
     */
    public function transactions()
    {
        //выполняем работу с амо
        $transactions = Transaction::where('status', '!=', 'OK')
            ->where('status', 'Новый контакт')
            ->orWhere('status', 'Найден контакт')
            ->limit(15)
            ->get();

        if($transactions->count() > 0) {

            $this->amocrm = (new Client())->init();

            foreach ($transactions as $transaction) {

                try {
                    $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                    if ($transaction->status == 'Новый контакт' &&
                        $transaction->comment == 'Создать сделку в УР') {

                        //создаем в УР
                        $lead = Leads::create($contact, [
                            'sale'      => $transaction->profile->balance,
                            'status_id' => 142,
                            'pipeline_id' => env('AMO_PIPELINE_ID'),
                        ], 'Новая сделка интеграция с Яндекс');

                        $transaction->lead_id   = $lead->id;
                        $transaction->status_id = $lead->status_id;
                        $transaction->status    = 'OK';
                        $transaction->comment   = 'УР для нового контакта';
                        $transaction->save();

                    } else {
                        //контакт уже был, основная логика
                        $lead = Leads::search($contact, $this->amocrm, env('AMO_PIPELINE_ID'));

                        //сколько дней прошло с транзакции
                        $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                        //сколько дней прошло с создания
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                        if($status_id == null) {

                            $transaction->status  = 'НЕ ОК';
                            $transaction->comment = $transaction->comment .' status_id не определен';
                            $transaction->save();

                            continue;
                        }

                        if($lead !== null) {
                            //есть активные сделка/ки у контакта
                            //TODO нюанс в проверке на перемещение назад | вроде бы и без проверки ок

                            $lead->status_id = $status_id;
                            $lead->sale = $transaction->profile->balance;
                            $lead->save();

                            $transaction->lead_id   = $lead->id;
                            $transaction->status_id = $lead->status_id;
                            $transaction->status    = 'Отслеживается';
                            $transaction->comment   = 'Найдена активная и обновлена';
                            $transaction->save();

                        } else {
                            //проверка 143 этапа (5 дней)
                            $leads = Leads::searchByStatus($contact, $this->amocrm, env('AMO_PIPELINE_ID'), 143);

                            if(count($leads) > 0) {

                                foreach ($leads as $lead) {

                                    $last_days = Profile::getLastDaysTimestamp($lead->updated_at);

                                    if($last_days < 5) {

                                        $transaction->status  = 'Нет активных есть закрытые';
                                        $transaction->comment = 'Нет активных, в 143 измененнная < 5 дней';
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
                            $transaction->status    = 'Нет активных и нет закрытых';
                            $transaction->comment   = 'В 143 нет измененных < 5 дней';
                            $transaction->save();
                        }
                    }
                } catch (\Exception $exception) {

                    $transaction->status = $exception->getMessage().' : '.$exception->getFile().' : '.$exception->getLine();
                    $transaction->save();
                }
            }
        }
    }
}
