<?php

namespace MM\Meros\Services\Integrations;

use Illuminate\Support\Facades\Http;

class HttpClient {
    public function send(array $request) {
        $client = Http::withHeaders($request['headers']);

        if ($request['format'] === 'json') {
            $client = $client->asJson();
        }

        if ($request['format'] === 'form') {
            $client = $client->asForm();
        }

        return $client->send(
            $request['method'],
            $request['url'],
            [
                'body' => $request['payload']
            ]
        );
    }
}