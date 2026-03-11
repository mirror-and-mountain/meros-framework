<?php 

namespace MM\Meros\Services\Integrations;

use MM\Meros\Models\IntegrationEndpoint;
use MM\Meros\Models\IntegrationConnection;

class IntegrationDriver {
    protected $integration;
    protected $requestBuilder;
    protected $httpClient;

    public function __construct(
        $integration,
        RequestBuilder $requestBuilder,
        HttpClient $httpClient
    ) {
        $this->integration = $integration;
        $this->requestBuilder = $requestBuilder;
        $this->httpClient = $httpClient;
    }

    public function call(string $endpointSlug, array $payload = []) {
        $endpoint = IntegrationEndpoint::where(
            'integration_id',
            $this->integration->id
        )
        ->where('slug', $endpointSlug)
        ->where('is_active', true)
        ->firstOrFail();

        $connection = IntegrationConnection::where(
            'integration_id',
            $this->integration->id
        )
        ->where('is_active', true)
        ->firstOrFail();

        $request = $this->requestBuilder->build(
            $this->integration,
            $connection,
            $endpoint,
            $payload
        );

        return $this->httpClient->send($request);
    }
}