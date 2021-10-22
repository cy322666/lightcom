<?php


namespace App\Services\Yandex\Services;


use App\Services\Yandex\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProfileCollection
{
    private $auth;

    const URL = 'https://fleet-api.taxi.yandex.net/v1/parks/driver-profiles/list';

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    private function getHeaders() : array
    {
        return [
            'X-Client-ID'  => $this->auth->client_id,
            'X-Api-Key'    => $this->auth->api_key,
            'Content-Type' =>  'application/json',
        ];
    }

    private function getPage($headers): ?array
    {
        $response = Http::withHeaders($headers)
            ->get(env('AVITO_BASE_URL') . self::URL . '?page=1&cursor=1');

        return json_decode($response->body(), true);
    }

    public function all()
    {
        $headers = self::getHeaders();

        $response = Http::withHeaders($headers)
            ->post(self::URL, [
                'query' => [
                    'park' => [
                        'id' => $this->auth->park_id,
                        'account' => [
                            'last_transaction_date' => [
                                'from' => date("Y-m-d", strtotime("-40 days")).'T00:00:00+0300'
                            ]
                        ]
                    ]
                ]
            ]);

        if($response->status() !== 200) {

            print_r($response->body());exit;

        } else
            return collect(json_decode($response->body(), true)['driver_profiles']);
    }

    private function checkAccess($code)
    {
        if ($code == 403) {

//            $auth = Auth::refresh_access($this->api->account);
//
//            if ($auth !== true) {
//
//                Log::error('Ошибка обновления ключей ' . $auth);
//
//            }
        }
    }

}
