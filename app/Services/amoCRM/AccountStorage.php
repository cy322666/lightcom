<?php


namespace App\Services;

use App\Models\Account;
use Ufee\Amo\Base\Storage\Oauth\AbstractStorage;
use Ufee\Amo\Oauthapi;

class AccountStorage extends AbstractStorage
{
    protected function initClient(Oauthapi $client) {

        parent::initClient($client);

        $client_id = $client->getAuth('client_id');


        $key = $client->getAuth('domain').'_'.$client_id;


        if($data = $this->getAccount($client_id)) {

            static::$_oauth[$key] = $data;
        }
    }

    public function setOauthData(Oauthapi $client, array $oauth) {

        parent::setOauthData($client, $oauth);

        $client_id = $client->getAuth('client_id');

        return $this->setOauth($client_id, $oauth);
    }

    private function getAccount(string $client_id)
    {
        $account = Account::where('client_id', $client_id)->first();

        return $account->toArray();
    }

    private function setOauth(string $client_id, array $oauth)
    {
        $account = Account::where('client_id', $client_id)->first();

        $account->access_token = $oauth['access_token'];
        $account->refresh_token = $oauth['refresh_token'];
        $account->expires_in = $oauth['expires_in'];
        $account->token_type = $oauth['token_type'];
        $account->save();

        return $account->toArray();
    }
}
