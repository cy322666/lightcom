<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Transaction;
use App\Services\amoCRM\Client;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ufee\Amo\Models\Lead;

class TransactionController extends Controller
{
    /**
     *  обрабатывает ожидающие транзакции
     *  создает/обновляет сделки по заложенной логике
     */
    //Профиль обновлен для отслеживания этапа
    public function transactions()
    {
        $transactions = Transaction::where('status', '!=', 'Не отслеживается')
            ->where('updated_at', '<', Carbon::now()->subMinutes(30)->format('Y-m-d H:i:s'))
            ->get();

        if($transactions->count() > 0) {

            $this->amocrm = (new Client())->init();

            foreach ($transactions as $transaction) {

                try {

                    /* ОТРАБОТКА ПРОФИЛЕЙ БЕЗ АКТИВНОСТЕЙ */
                    if($transaction->profile->last_transaction_date == null) {

                        //сколько дней назад создан
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        //ждущие 2 дня после создания
                        if($last_created_days == 2) {

                            Log::info(__METHOD__.' : Не активная транзакция создана 2 дня назад : '.$transaction->id.' $last_created_days : '.$last_created_days);

                            if($transaction->lead_id == null) {

                                $contact = Contacts::get($this->amocrm, $transaction->profile->contact_id);

                                $lead = Leads::create($contact, [
                                    'sale'        => $transaction->profile->balance,
                                    'status_id'   => env('AMO_STATUS_ID_2_DAYS'),
                                    'pipeline_id' => env('AMO_PIPELINE_ID'),
                                ], 'Новая сделка интеграция с Яндекс');

                                $lead->attachTag($transaction->profile->park_id);
                                $lead->save();

                                $transaction->lead_id = $lead->id;
                                $transaction->status  = 'Отслеживается';
                                $transaction->comment = 'Ждала второго дня';
                                $transaction->save();

                                $this->check($contact);
                            }
                            //не актуальные профили
                        } elseif($last_created_days > 2) {

                            $transaction->comment = 'Старый профиль без активности';
                            $transaction->status  = 'Не отслеживается';

                            $transaction->profile->status = 'Не отслеживается';
                            $transaction->push();

                            //новый профиль (< 2d) без активности
                        } elseif($last_created_days < 2) {

                            $transaction->comment = 'Создан < 2 дней без активности';
                            $transaction->status  = 'Отслеживается';
                            $transaction->save();
                        }

                        //не актуальные профили
                    } elseif(Profile::getLastDays($transaction->profile->last_transaction_date) > 40) {

                        $transaction->comment = 'Лимит времени активности';
                        $transaction->status  = 'Не отслеживается';

                        $transaction->profile->status = 'Не отслеживается';
                        $transaction->push();

                        /* СОЗДАНИЕ В 142 ДЛЯ НОВЫХ КОНТАКТО */
                    } elseif ($transaction->comment == 'Создать сделку в УР') {

                        $contact = Contacts::get($this->amocrm, $transaction->profile->contact_id);

                        $lead = Leads::create($contact, [
                            'sale'      => $transaction->profile->balance,
                            'status_id' => 142,
                            'pipeline_id' => env('AMO_PIPELINE_ID'),
                        ], 'Новая сделка интеграция с Яндекс');

                        $lead->attachTag($transaction->profile->park_id);
                        $lead->save();

                        $transaction->lead_id   = $lead->id;
                        $transaction->status_id = $lead->status_id;
                        $transaction->status    = 'Не отслеживается';
                        $transaction->comment   = 'УР для нового контакта';
                        $transaction->save();

                        $this->check($contact);

                        /* ОТРАБОТКА НОВЫХ ПРОФИЛЕЙ НО С СУЩЕСТВУЮЩИМ КОНТАКТОМ В АМО */
                    } elseif($transaction->status == 'Найден контакт') {

                        $contact = Contacts::get($this->amocrm, $transaction->profile->contact_id);

                        //сколько дней прошло с транзакции
                        $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                        //сколько дней прошло с создания
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                        //возможно косяк с условиями
                        if($status_id == null) {

                            $transaction->status  = 'Отслеживается';
                            $transaction->comment = 'Активность < 2 дней';
                            $transaction->save();

                        } else {

                            $lead = Leads::search($contact, $this->amocrm, env('AMO_PIPELINE_ID'));

                            if($lead !== null) {

                                $lead->attachTag($transaction->profile->park_id);

                                $lead->status_id = $status_id;
                                $lead->sale = $transaction->profile->balance;
                                $lead->save();

                                $transaction->lead_id   = $lead->id;
                                $transaction->status_id = $lead->status_id;
                                $transaction->status    = 'Отслеживается';
                                $transaction->comment   = 'Найдена активная и обновлена';
                                $transaction->save();

                                $this->check($contact);

                                //нет активной, проверяем условия по закрытым
                            } else {
                                //проверка 143 этапа + изменения за 5 дней
                                $leads = Leads::searchByStatus($contact, $this->amocrm, env('AMO_PIPELINE_ID'), 143);

                                if(count($leads) > 0) {

                                    foreach ($leads as $lead) {

                                        $last_days = Profile::getLastDaysTimestamp($lead->updated_at);

                                        if($last_days < 5) {

                                            $transaction->status  = 'Отслеживается';
                                            $transaction->comment = 'Нет активных, в 143 измененнная < 5 дней';
                                            $transaction->save();

                                            break;
                                        }
                                    }
                                    //нет закрытых сделок, создаем новую
                                } else {
                                    $lead = Leads::create($contact, [
                                        'sale'      => $transaction->profile->balance,
                                        'status_id' => $status_id,
                                    ], 'Новая сделка интеграция с Яндекс');

                                    $lead->attachTag($transaction->profile->park_id);
                                    $lead->save();

                                    $transaction->lead_id   = $lead->id;
                                    $transaction->status_id = $lead->status_id;
                                    $transaction->status    = 'Отслеживается';
                                    $transaction->comment   = 'Нет активных, в 143 нет измененных < 5 дней';
                                    $transaction->save();

                                    $this->check($contact);
                                }
                            }
                        }
                        /* ОТРАБОТКА ОТСЛЕЖИВАЕМЫХ ПРОФИЛЕЙ */
                    } elseif($transaction->status == 'Профиль обновлен') {

                        $contact = Contacts::get($this->amocrm, $transaction->profile->contact_id);

                        //сколько дней прошло с транзакции
                        $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                        //сколько дней прошло с создания
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                        if($status_id !== null) {

                            $lead = Leads::get($this->amocrm, $transaction->lead_id);

                            Leads::update($lead, [
                                'status_id' => $status_id,
                                'sale'      => $transaction->profile->balance,
                            ], []);

                            $transaction->status_id = $status_id;

                            if($status_id == env('AMO_STATUS_ID_15_DAYS')) {

                                $transaction->status = 'Отслеживается';
                            } else {

                                $transaction->profile->status = 'Не отслеживается';
                                $transaction->status = 'Не отслеживается';
                            }

                            $transaction->comment = 'Смена этапа отслеживаемой сделки';
                            $transaction->push();

                            $this->check($contact);
                        }
                    }

                } catch (\Exception $exception) {

                    $transaction->status = $exception->getMessage().' : '.$exception->getFile().' : '.$exception->getLine();
                    $transaction->save();
                }
            }
        }
    }

    private function check($contact)
    {
        if($contact == null) return;

        $lead = Leads::search($contact, $this->amocrm, 3697717);

        if($lead) {

            $lead->status_id = 142;
            $lead->save();

            Log::warning('Смена сделки '.$lead->id);
        }
    }
}
