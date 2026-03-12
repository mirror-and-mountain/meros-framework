<?php

namespace MM\Meros\Services\Integrations;

use MM\Meros\Models\Integration;
use MM\Meros\Models\IntegrationEndpoint;
use MM\Meros\Models\IntegrationConnection;

class RequestBuilder {
    protected AuthResolver $authResolver;

    public function __construct(AuthResolver $authResolver) {
        $this->authResolver = $authResolver;
    }

    public function build(
        Integration $integration,
        IntegrationConnection $connection,
        IntegrationEndpoint $endpoint,
        array $payload = []
    ): array {

        $url =
            ($connection->instance_url ?? '') .
            rtrim($integration->api_base_uri, '/') . '/' . ltrim($integration->api_version, '/') . '/' .
            $endpoint->uri;

        $headers = $this->authResolver->resolve(
            $integration,
            $connection
        );

        return [
            'method'  => $endpoint->method,
            'url'     => $url,
            'headers' => $headers,
            'payload' => $payload,
            'format'  => $endpoint->format,
        ];
    }
}