<?php 

namespace MM\Meros\App\Integrations;

use MM\Meros\Models\Integration;
use MM\Meros\Models\IntegrationEndpoint;
use MM\Meros\Models\IntegrationConnection;
use MM\Meros\App\Integrations\RequestBuilder;
use MM\Meros\App\Integrations\HttpClient;

class IntegrationDriver {
    protected Integration $integration;

    protected ?IntegrationConnection $connection = null;

    protected RequestBuilder $requestBuilder;

    protected HttpClient $httpClient;

    public function __construct(
        Integration $integration,
        RequestBuilder $requestBuilder,
        HttpClient $httpClient
    ) {
        $this->integration = $integration;
        $this->requestBuilder = $requestBuilder;
        $this->httpClient = $httpClient;
    }

    public function call(string $endpointSlug, array $payload = []): mixed {
        $endpoint   = $this->resolveEndpoint($endpointSlug);
        $connection = $this->resolveConnection();
        $payload    = $this->transformPayload($payload, $endpoint);

        $request = $this->requestBuilder->build(
            $this->integration,
            $connection,
            $endpoint,
            $payload
        );

        $request  = $this->beforeSend($request, $endpoint, $connection);
        $response = $this->httpClient->send($request);

        return $this->afterSend($response, $endpoint);
    }

    protected function resolveEndpoint(string $slug): IntegrationEndpoint {
        return IntegrationEndpoint::where(
            'integration_id',
            $this->integration->id
        )
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();
    }

    protected function resolveConnection(): IntegrationConnection {
        if ($this->connection) {
            return $this->connection;
        }

        return $this->connection = IntegrationConnection::where(
            'integration_id',
            $this->integration->id
        )
        ->where('is_active', true)
        ->firstOrFail();
    }

    protected function transformPayload(
        array $payload,
        IntegrationEndpoint $endpoint
    ): array {
        return $payload;
    }

    protected function beforeSend(
        array $request,
        IntegrationEndpoint $endpoint,
        IntegrationConnection $connection
    ): array {
        return $request;
    }

    protected function afterSend(
        mixed $response,
        IntegrationEndpoint $endpoint
    ): mixed {
        return $response;
    }
}