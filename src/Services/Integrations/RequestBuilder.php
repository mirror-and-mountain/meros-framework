<?php 

namespace MM\Meros\Services\Integrations;

class RequestBuilder {
    public function build($integration, $connection, $endpoint, $payload) {
        $url =
            $connection->instance_url .
            $integration->api_base_uri .
            '/' .
            $integration->api_version .
            $endpoint->uri;

        $headers = app(AuthResolver::class)
            ->resolve($integration, $connection);

        return [
            'method' => $endpoint->method,
            'url' => $url,
            'headers' => $headers,
            'payload' => $payload,
            'format' => $endpoint->format
        ];
    }
}