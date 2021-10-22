<?php

namespace App\Services\amoCRM\Helpers;

use App\Models\Api\Viewer;
use App\Services\amoCRM\Client;

abstract class Contacts extends Client
{
    public static function search($model, $client)
    {
        $contacts = null;

        if($model->phone)
            $contacts = $client->service
                ->contacts()
                ->searchByPhone($model->phone);

        if($contacts != null && !$contacts->first()) {

            if($model->email)
                $contacts = $client->service
                    ->contacts()
                    ->searchByEmail($model->email);
        }

        if($contacts != null && $contacts->first())
            return $contacts->first();
        else
            return null;
    }

    public static function update($contact, $model, $name = null)
    {
        if($model->phone)
            $contact->cf('Телефон')->setValue($model->phone);

        if($model->email)
            $contact->cf('Email')->setValue($model->email);

        if($name)
            $contact->name = $name;

        $contact->save();

        return $contact;
    }

    public static function updateParams($contact, $params = [])
    {
        if($params['responsible_user_id'])
            $contact->responsible_user_id = $params['responsible_user_id'];

        $contact->save();

        return $contact;
    }

    public static function create($amoapi, $fields = [], $name = 'Неизвестно')
    {
        $contact = $amoapi->service
            ->contacts()
            ->create();

        $contact->responsible_user_id = $amoapi::DEFAULT_RESPONSIBLE;

        $contact->name = $name;

        if($client->phone)
            $contact->cf('Телефон')->setValue($client->phone);

        if($client->email)
            $contact->cf('Email')->setValue($client->email);

        $contact->save();

        return $contact;
    }

    public static function get($client, $id)
    {
        return $client->service->contacts()->find($id);
    }
}
