<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Transaction;
use App\Services\amoCRM\Client;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ufee\Amo\Models\Lead;

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
            ->orWhere('status', 'Новый контакт')
            ->orWhere('status', 'Найден контакт')
            ->get();

        if($transactions->count() > 0) {

            $this->amocrm = (new Client())->init();

            foreach ($transactions as $transaction) {

                try {
                    $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                    if($transaction->profile->last_transaction_date == null) {

                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        if($last_created_days == 2) {

                            Log::info(__METHOD__.' : Не активная транзакция создана 2 дня назад : '.$transaction->id.' $last_created_days : '.$last_created_days);

                            $lead = Leads::create($contact, [
                                'sale'        => $transaction->profile->balance,
                                'status_id'   => env('AMO_STATUS_ID_2_DAYS'),
                                'pipeline_id' => env('AMO_PIPELINE_ID'),
                            ], 'Новая сделка интеграция с Яндекс');

                            $lead->attachTag($transaction->profile->park_id);
                            $lead->save();

                            $transaction->lead_id = $lead->id;
                            $transaction->status = 'OK';
                            $transaction->comment = 'Обновлена ожидающая';
                            $transaction->save();

                            //dd($transaction->id);

                        } elseif($last_created_days < 2) {

                            $transaction->comment = 'Ожидающая < 2 дней';
                            $transaction->save();
                        }

                        continue;
                    }

                    if ($transaction->status == 'Новый контакт' &&
                        $transaction->comment == 'Создать сделку в УР') {

                        //создаем в УР
                        $lead = Leads::create($contact, [
                            'sale'      => $transaction->profile->balance,
                            'status_id' => 142,
                            'pipeline_id' => env('AMO_PIPELINE_ID'),
                        ], 'Новая сделка интеграция с Яндекс');

                        $lead->attachTag($transaction->profile->park_id);
                        $lead->save();

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

                        Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' $last_days : '.$last_days);
                        Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' $last_created_days : '.$last_created_days);
                        Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' $status_id : '.$status_id);

                        if($status_id == null) {

                            Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' была активность < 2 дней');

                            $transaction->status  = 'Ждет крона';
                            $transaction->comment = 'Активность < 2 дней';
                            $transaction->save();

                            continue;

                        } elseif($lead !== null) {
                            //есть активные сделка/ки у контакта
                            //TODO нюанс в проверке на перемещение назад | вроде бы и без проверки ок

                            $lead->attachTag($transaction->profile->park_id);

                            $lead->status_id = $status_id;
                            $lead->sale = $transaction->profile->balance;
                            $lead->save();

                            $transaction->lead_id   = $lead->id;
                            $transaction->status_id = $lead->status_id;
                            $transaction->status    = 'Отслеживается';
                            $transaction->comment   = 'Найдена активная и обновлена';
                            $transaction->save();

                            Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' успешно отработана');

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

                                        Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' нет активных, есть измененнная < 5 дней');

                                        continue 2;
                                    }
                                }
                                //тут нужно, но и так ок
                            }
                            //нет сделок, надо создать новую

                            Log::info(__METHOD__.' : Новая транзакция : '.$transaction->id.' нет активных, нет подходящих закрытых, создается сделка');

                            $lead = Leads::create($contact, [
                                'sale'      => $transaction->profile->balance,
                                'status_id' => $status_id,
                            ], 'Новая сделка интеграция с Яндекс');

                            $lead->attachTag($transaction->profile->park_id);
                            $lead->save();

                            $transaction->lead_id   = $lead->id;
                            $transaction->status_id = $lead->status_id;
                            $transaction->status    = 'Отслеживается';
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

    public function transactions_updated()
    {
        $this->amocrm = (new Client())->init();

        $transactions = Transaction::where('status', 'Профиль обновлен')->get();

        if($transactions->count() > 0) {

            foreach ($transactions as $transaction) {

                try {
                    //то что создали по новым/добавленным профилям больше не отслеживаем
                    if($transaction->status_id !== 142) {

                        //сколько дней прошло с транзакции
                        $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                        //сколько дней прошло с создания
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                        Log::info(__METHOD__.' : Обновленная транзакция : '.$transaction->id.' $last_days : '.$last_days);
                        Log::info(__METHOD__.' : Обновленная транзакция : '.$transaction->id.' $last_created_days : '.$last_created_days);
                        Log::info(__METHOD__.' : Обновленная транзакция : '.$transaction->id.' $status_id : '.$status_id);

                        if($status_id == null) {

                            $transaction->status  = 'OK';
                            $transaction->comment = 'Обновление профиля, активность < 5 дней';

                            $transaction->profile->status = 'OK';
                            $transaction->push();

                            continue;
                        }

                        //уже есть отслеживаемая сделка
                        if($transaction->lead_id !== null) {

                            $lead = Leads::get($this->amocrm, $transaction->lead_id);

                            if($lead->status_id == $status_id) {

                                $transaction->comment = 'Профиль обновлен, но статус прежний';

                            } else {

                                $lead->status_id = $status_id;
                                $lead->save();

                                $transaction->comment = 'Профиль обновлен, статус обновлен';
                            }

                            $lead->attachTag($transaction->profile->park_id);
                            $lead->save();

                            $transaction->status  = 'OK';
                            $transaction->profile->status = 'OK';
                            $transaction->push();

                        } else {

                            $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                            $lead = Leads::search($contact, $this->amocrm, env('AMO_PIPELINE_ID'));

                            if($lead !== null) {
                                //есть активные сделка/ки у контакта
                                //TODO нюанс в проверке на перемещение назад | вроде бы и без проверки ок
                                $lead->status_id = $status_id;
                                $lead->sale = $transaction->profile->balance;

                                $lead->attachTag($transaction->profile->park_id);

                                $lead->save();

                                $transaction->lead_id   = $lead->id;
                                $transaction->status_id = $lead->status_id;
                                $transaction->status    = 'Отслеживается';
                                $transaction->comment   = 'Профиль обновлен, найдена активная и обновлена';
                                $transaction->profile->status = 'OK';
                                $transaction->push();

                            } else {
                                //проверка 143 этапа (5 дней)
                                $leads = Leads::searchByStatus($contact, $this->amocrm, env('AMO_PIPELINE_ID'), 143);

                                if(count($leads) > 0) {

                                    foreach ($leads as $lead) {

                                        $last_days = Profile::getLastDaysTimestamp($lead->updated_at);

                                        if($last_days < 5) {

                                            $transaction->status  = 'Нет активных есть закрытые';
                                            $transaction->comment = 'Нет активных, в 143 измененнная < 5 дней';
                                            $transaction->profile->status = 'OK';
                                            $transaction->push();

                                            Log::info(__METHOD__.' : Обновленная транзакция : '.$transaction->id.' нет активных, есть измененнная < 5 дней');

                                            continue 2;
                                        }
                                    }
                                    //тут нужно, но и так ок
                                }
                                //нет сделок, надо создать новую
                                Log::info(__METHOD__.' : Обновленная транзакция : '.$transaction->id.' нет активных, нет подходящих закрытых, создается сделка');

                                $lead = Leads::create($contact, [
                                    'sale'      => $transaction->profile->balance,
                                    'status_id' => $status_id,
                                ], 'Новая сделка интеграция с Яндекс');

                                $lead->attachTag($transaction->profile->park_id);
                                $lead->save();

                                $transaction->lead_id   = $lead->id;
                                $transaction->status_id = $lead->status_id;
                                $transaction->status    = 'Отслеживается';
                                $transaction->comment   = 'Профиль обновлен | В 143 нет измененных < 5 дней';
                                $transaction->profile->status = 'OK';
                                $transaction->push();
                            }
                        }
                    } else {
                        $transaction->status = 'OK';
                        $transaction->comment = 'Не отслеживается';
                        $transaction->profile->status = 'OK';
                        $transaction->push();
                    }
                } catch (\Exception $exception) {

                    $transaction->status = $exception->getMessage().' : '.$exception->getFile().' : '.$exception->getLine();
                    $transaction->save();
                }
            }
        }

        $transactions = Transaction::where('comment', 'Активность < 2 дней')->get();

        if($transactions->count() > 0) {

            foreach ($transactions as $transaction) {

                try {

                    $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

                    if($last_days == 2) {

                        //сколько дней прошло с создания
                        $last_created_days = Profile::getLastDays($transaction->profile->created_date);

                        $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

                        Log::info(__METHOD__.' : Чек активности < 2 : '.$transaction->id.' $last_days : '.$last_days);
                        Log::info(__METHOD__.' : Чек активности < 2 : '.$transaction->id.' $last_created_days : '.$last_created_days);
                        Log::info(__METHOD__.' : Чек активности < 2 : '.$transaction->id.' $status_id : '.$status_id);

                        if($status_id != null) {

                            if($transaction->lead_id  == null) {

                                $contact = Contacts::get($this->amocrm, $transaction->contact_id);

                                $lead = Leads::create($contact, [
                                    'sale'      => $transaction->profile->balance,
                                    'status_id' => $status_id,
                                ], 'Новая сделка интеграция с Яндекс');

                                $lead->attachTag($transaction->profile->park_id);
                                $lead->save();

                                $transaction->lead_id   = $lead->id;
                                $transaction->status_id = $lead->status_id;
                                $transaction->status    = 'Отслеживается';
                                $transaction->comment   = 'Активная < 2 отработана';
                                $transaction->profile->status = 'OK';
                                $transaction->push();
dd('exit');
                            } else {
                                Log::error(__METHOD__ . ' : Чек активности < 2 сделки быть не должно, но она есть : ' . $transaction->id . ' $status_id : ' . $status_id);
                            }
                        } else {
                            Log::error(__METHOD__.' : Чек активности < 2 статус не определен, хотя 2 дня прошло : '.$transaction->id.' $status_id : '.$status_id);
                        }
                    }

                } catch (\Exception $exception) {

                    $transaction->status = $exception->getMessage().' : '.$exception->getFile().' : '.$exception->getLine();
                    $transaction->save();
                }
            }
        }
    }

    public function check_status()
    {
        $transactions = Transaction::where('status_id', '!=', 142)
            ->where('lead_id', '!=', null)
            ->get();

        $this->amocrm = (new Client())->init();

        foreach ($transactions as $transaction) {

            //сколько дней прошло с транзакции
            $last_days = Profile::getLastDays($transaction->profile->last_transaction_date);

            //сколько дней прошло с создания
            $last_created_days = Profile::getLastDays($transaction->profile->created_date);

            $status_id = Profile::getStatusLastDays($last_days, $last_created_days);

            Log::info(__METHOD__.' : check транзакция : '.$transaction->id.' $last_days : '.$last_days);
            Log::info(__METHOD__.' : check транзакция : '.$transaction->id.' $last_created_days : '.$last_created_days);
            Log::info(__METHOD__.' : check транзакция : '.$transaction->id.' $status_id : '.$status_id);

            if($status_id == null) {

                continue;
            } else {

                $lead = Leads::get($this->amocrm, $transaction->lead_id);

                if($lead) {

                    if($lead->status_id != $status_id) {

                        Log::info(__METHOD__.' : check транзакция : этап изменен по крону, дней с транзакции : '.$last_days);

                        $lead->status_id = $status_id;
                        $lead->save();

                        $transaction->status = 'OK';
                        $transaction->comment = 'Статус обновлен по крону';
                        $transaction->save();
                    }
                } else
                    Log::error(__METHOD__.' не получена сделка по api');
            }
        }
    }
}
