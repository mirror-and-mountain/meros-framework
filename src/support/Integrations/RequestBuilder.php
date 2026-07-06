<?php

namespace MM\Meros\Support\Integrations;

use MM\Meros\App\Models\IntegrationConnection;

/**
 * Class RequestBuilder
 *
 * This class is responsible for constructing HTTP request parameters for integration endpoints.
 * It builds the request URL, method, headers, payload, and format based on the provided integration,
 * connection, and endpoint information.
 */
class RequestBuilder {
    public function __construct(
        protected AuthResolver $authResolver
    ) {
    }

    /**
     * Builds the HTTP request parameters for a given integration, connection, and endpoint.
     *
     * @param mixed $integration The integration instance or object.
     * @param IntegrationConnection $connection The associated integration connection.
     * @param mixed $endpoint The endpoint information, which may include method, URI, and format.
     * @param array $payload Optional payload data to include in the request.
     *
     * @return array An associative array containing the request parameters: method, url, headers, payload, and format.
     */
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

    /**
     * Builds the full URL for a given integration and endpoint.
     *
     * @param mixed $integration The integration instance or object.
     * @param mixed $endpoint The endpoint information, which may include method, URI, and format.
     *
     * @return string The constructed URL.
     */
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