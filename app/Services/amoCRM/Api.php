<?php


namespace App\Services\amoCRM;

use App\Models\Account;
use Ufee\Amo\Oauthapi;

class Api
{
    public $service;

    public function install()
    {
        $this->service = \Ufee\Amo\Oauthapi::setInstance([
            'domain'        => env('AMO_DOMAIN'),
            'client_id'     => env('AMO_CLIENT_ID'),
            'client_secret' => env('AMO_CLIENT_SECRET'),
            'redirect_uri'  => env('AMO_REDIRECT_URI'),
        ]);

        \Ufee\Amo\Services\Account::setCacheTime(3600);

        $this->service->queries->setDelay(1);
        $this->service->queries->cachePath(storage_path('amocrm/cache'));

        try {

            $this->service = \Ufee\Amo\Oauthapi::getInstance(env('AMO_CLIENT_ID'));

            $this->service->account;

        } catch (\Exception $exception) {

print_r($exception->getMessage().' '.$exception->getFile().' '.$exception->getLine());

            $this->service->fetchAccessToken(env('AMO_CODE'));
        }

        return $this;
    }
}
