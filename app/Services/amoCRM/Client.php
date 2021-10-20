<?php


namespace App\Services\amoCRM;

use App\Models\Account;
use App\Services\amoCRM\Helpers\Contacts;
use App\Services\amoCRM\Helpers\Leads;
use App\Services\amoCRM\Helpers\Notes;
use Illuminate\Support\Facades\Log;
use Ufee\Amo\Base\Storage\Oauth\AbstractStorage;
use Ufee\Amo\Base\Storage\Oauth\FileStorage;
use Ufee\Amo\Oauthapi;
use App\Services\AccountStorage;

class Client
{
    public $service;

    public function init(string $widget = null): Client
    {
        $account = Account::where('service', 'amocrm')->first();

        Oauthapi::setOauthStorage(new AccountStorage([]));

        $this->service = Oauthapi::setInstance([
            'domain'        => $account->subdomain,
            'client_id'     => $account->client_id,
            'client_secret' => $account->client_secret,
            'redirect_uri'  => $account->redirect_uri,//TODO env защить
        ]);

        \Ufee\Amo\Services\Account::setCacheTime(3600);

        $this->service->queries->setDelay(0.5);
        $this->service->queries->cachePath(storage_path('amocrm/cache'));

        try {

            $this->service->account;

        } catch (\Exception $exception) {

            if ($account->refresh_token) {

                $this->service->onAccessTokenRefresh(function ($access, $account) {

                    $account->access_token  = $access['access_token'];
                    $account->refresh_token = $access['refresh_token'];
                    $account->save();
                });

            } else {

                try {

                    $access = $this->service->fetchAccessToken(env('AMO_CODE'));

                    $account->expires_in = $access['expires_in'];
                    $account->access_token = $access['access_token'];
                    $account->refresh_token = $access['refresh_token'];
                    $account->save();

                } catch (\Exception $exception) {

                    dd($exception->getMessage());
                }
            }
        }

//            $this->service->fetchAccessToken($account->code);
//
//            if($account->refresh_token)
//
//                $this->service->refreshAccessToken($account->refresh_token);

        return $this;
    }
}
