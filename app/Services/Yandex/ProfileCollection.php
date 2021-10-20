<?php


namespace App\Services\Yandex\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProfileCollection
{
    use Response;

    private $api;

    const URL = 'negotiations';

    //https://fleet-api.taxi.yandex.net/v1/parks/driver-profiles/list

    public function __construct($api)
    {
        $this->api = $api;

        $this->checkAccess();
    }

    private function getHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->api->account->access_token,
            'Accept' => 'application/json',
        ];
    }

    private function getPage($headers): ?array
    {
        $response = Http::withHeaders($headers)
            ->get(env('AVITO_BASE_URL') . self::URL . '?page=1&cursor=1');

        self::checkAccess($response->status());

        return json_decode($response->body(), true);
    }

    private function getProfiles(array $headers, int $page, int $cursor): ?array
    {
        $response = Http::withHeaders($headers)
            ->get(env('AVITO_BASE_URL') . self::URL . '?page=' . $page . '&cursor=' . $cursor);

        $data = json_decode($response->body(), true);

        if (key_exists('result', $data) && count($data['result']) > 0) {

            return $data['result'];
        } else
            return null;
    }

    public function all()
    {
        try {

            $headers = $this->getHeaders();

            $response = $this->getPage($headers);

            if (!empty($response['meta'])) {

                $page = $response['meta']['pages'] != 1 ? $response['meta']['pages'] - 5 : 1;
                $cursor = $response['meta']['cursor'];

                return $this->getNegotiations($headers, $page, $cursor);
            }

        } catch (\Exception $exception) {

            Log::error(__METHOD__ . ' : ' . $exception->getFile() . ' ' . $exception->getMessage());
        }
    }

    private function checkAccess($code)
    {
        if ($code == 403) {

            $auth = Auth::refresh_access($this->api->account);

            if ($auth !== true) {

                Log::error('Ошибка обновления ключей ' . $auth);

            }
        }
    }

}
