<?php

namespace MM\Meros\App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class HttpClient {
    public function send(array $request): Response {
        $client = Http::withHeaders($request['headers'] ?? []);

        $method  = strtoupper($request['method']);
        $url     = $request['url'];
        $payload = $request['payload'] ?? [];
        $format  = $request['format'] ?? null;

        if ($format === 'json') {
            $client = $client->asJson();

            return $client->send($method, $url, [
                'json' => $payload
            ]);
        }

        if ($format === 'form') {
            $client = $client->asForm();

            return $client->send($method, $url, [
                'form_params' => $payload
            ]);
        }

        return $client->send($method, $url, [
            'body' => $payload
        ]);
    }
}