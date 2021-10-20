<?php


namespace App\Services\amoCRM\Helpers;


use App\Models\Api\Setting;
use App\Services\amoCRM\Client;
use Illuminate\Support\Facades\Log;

abstract class Leads
{
    public static function search($contact, $client, int $pipeline_id = null)
    {
        if($contact->leads) {

            foreach ($contact->leads->toArray() as $lead) {

                if ($lead['status_id'] != 143 &&
                    $lead['status_id'] != 142) {

                    if($pipeline_id != null && $lead['pipeline_id'] == $pipeline_id) {

                        $lead = $client->service
                            ->leads()
                            ->find($lead['id']);

                        return $lead;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param $statuses array массив статусов для поиска в порядке приоритета
     * @param $responsible_user_id integer ответственный для проверке в сделке
     */
    public static function searchByStatus($amoApi, $statuses_pipelines, int $responsible_user_id)
    {
        $arrayLeads = [];

        foreach ($statuses_pipelines as $statuses_pipeline) {

            if($statuses_pipeline['status_id'] == '') continue;
            
            $query = [
                'filter[statuses][0][status_id]'   => $statuses_pipeline['status_id'],
                'filter[statuses][0][pipeline_id]' => $statuses_pipeline['pipeline_id'],
                'limit' => 500,
            ];
    
            if($statuses_pipeline['pipeline_id'] != 3987300)
                $query = array_merge($query, ['filter[responsible_user_id]' => $responsible_user_id]);
            
            $leads = $amoApi->service->ajax()->get('/api/v4/leads', $query);
            
            if($leads !== true ) {
    
                $arrayLeads = array_merge($arrayLeads, $leads->_embedded->leads);
                
                if($leads->_page > 1) {
                    
                    for($i = 2; $i < $leads->_page; $i++) {
    
                        if($statuses_pipeline['status_id'] == '') continue;
    
                        $query = array_merge($query, ['page' => $i]);
    
                        if($statuses_pipeline['pipeline_id'] != 3987300)
                            $query = array_merge($query, ['filter[responsible_user_id]' => $responsible_user_id]);
    
                        $leads = $amoApi->service->ajax()->get('/api/v4/leads', $query);
    
                        if($leads !== true ) {
                            
                            $arrayLeads = array_merge($arrayLeads, $leads->_embedded->leads);
                        }
                    }
                }
            }
        }

        if(count($arrayLeads) > 0) {
    
            Log::info(__METHOD__.' staff получил '.count($arrayLeads).' сделок для перевода на админа');
    
            return (object)$arrayLeads;
            
        } else
            return null;
    }

    public static function create($contact, array $params, string $leadname)
    {
        $lead = $contact->createLead();

        $lead->name = $leadname;

        if(!empty($params['sale']))
            $lead->sale = $params['sale'];

        if(!empty($params['responsible_user_id']))
            $lead->responsible_user_id = $params['responsible_user_id'];

        if(!empty($params['status_id']))
            $lead->status_id = $params['status_id'];

        $lead->contacts_id = $contact->id;
        $lead->save();

        return $lead;
    }

    public static function update($lead, array $params, array $fields)
    {
        try {
            
            if($fields) {
    
                foreach ($fields as $key => $field) {
    
                    $lead->cf($key)->setValue($field);
                }
            }
    
            if(!empty($params['responsible_user_id']))
                $lead->responsible_user_id = $params['responsible_user_id'];
    
            if(!empty($params['status_id']))
                $lead->status_id = $params['status_id'];
    
            $lead->updated_at = time() + 2;
            $lead->save();
    
            return $lead;
        
        } catch (\Exception $exception) {
            
            Log::error(__METHOD__. ' : ошибка обновления '.$exception->getMessage(). ' , сделка : '.$lead->id);
        }
    }

    public static function get($client, $id)
    {
        try {
            
            $lead = $client->service->leads()->find($id);
    
            return $lead;
            
        } catch (\Exception $exception) {
            
            sleep(2);
            
            Log::error(__METHOD__. ' : '.$exception->getMessage(). ' , сделка : '.$id);
        }
    }
}
