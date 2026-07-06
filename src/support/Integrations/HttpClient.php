<?php

namespace MM\Meros\Support\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A simple HTTP client for sending requests to external services.
 */
class HttpClient {
    /**
     * Sends an HTTP request based on the provided request array.
     *
     * @param array $request The request details, including method, url, headers, payload, and format.
     * @return Response The response from the HTTP request.
     */
    public function send(array $request): Response {
        $client = Http::withHeaders($request['headers'] ?? []);

        $method  = strtoupper($request['method']);
        $url     = $request['url'];
        $payload = $request['payload'] ?? [];
        $format  = $request['format'] ?? null;

        if ($format === 'json') {
            $client = $client->asJson();

            return $client->send($method, $url, [
                'json' => $payload,
            ]);
        }

        if ($format === 'form') {
            $client = $client->asForm();

            return $client->send($method, $url, [
                'form_params' => $payload,
            ]);
        }

        return $client->send($method, $url, [
            'body' => $payload,
        ]);
    }
}