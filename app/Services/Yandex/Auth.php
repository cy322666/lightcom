<?php

namespace App\Services\Yandex;

use App\Models\Account;

class Auth
{
    public $client_id;
    public $api_key;
    public $park_id;

    public function setAccess(Account $account): Auth
    {
        $this->client_id = $account->client_id;
        $this->api_key   = $account->api_key;
        $this->park_id   = $account->park_id;

        return $this;
    }
}
