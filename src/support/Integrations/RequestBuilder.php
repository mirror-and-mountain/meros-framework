<?php

namespace MM\Meros\Support\Integrations;

use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\IntegrationConnection;

class RequestBuilder {
    public function __construct(
        protected AuthResolver $authResolver
    ) {
    }

    public function build(
        mixed $integration,
        IntegrationConnection $connection,
        mixed $endpoint,
        array $payload = []
    ): array {
        $url = $this->buildUrl($integration, $endpoint);

        return [
            'method'  => $endpoint->method,
            'url'     => $url,
            'headers' => $this->authResolver->resolve($integration, $connection),
            'payload' => $payload,
            'format'  => $endpoint->format,
        ];
    }

    protected function buildUrl(mixed $integration, mixed $endpoint): string {
        $baseUri = '';
        $version = '';

        if (is_object($integration)) {
            if (method_exists($integration, 'getBaseUri')) {
                $baseUri = (string) $integration->getBaseUri();
            } elseif (isset($integration->settings['api_base_uri'])) {
                $baseUri = (string) $integration->settings['api_base_uri'];
            }

            if (method_exists($integration, 'getApiVersion')) {
                $version = (string) $integration->getApiVersion();
            } elseif (isset($integration->settings['api_version'])) {
                $version = (string) $integration->settings['api_version'];
            }
        }

        $url = rtrim($baseUri, '/');

        if ($version !== '') {
            $url .= '/' . trim($version, '/');
        }

        $endpointUri = is_object($endpoint) && isset($endpoint->uri)
            ? (string) $endpoint->uri
            : (string) $endpoint;

        if ($endpointUri !== '') {
            $url .= '/' . ltrim($endpointUri, '/');
        }

        return $url;
    }
}