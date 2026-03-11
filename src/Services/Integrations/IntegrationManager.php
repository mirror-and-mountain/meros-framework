<?php

namespace MM\Meros\Services\Integrations;

use MM\Meros\Models\Integration;

class IntegrationManager {

    protected $requestBuilder;
    protected $httpClient;

    public function __construct(
        RequestBuilder $requestBuilder,
        HttpClient $httpClient
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->httpClient = $httpClient;
    }

    public static function driver(string $slug): IntegrationDriver {
        return app(self::class)->resolveDriver($slug);
    }

    protected function resolveDriver(string $slug): IntegrationDriver {
        $integration = Integration::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return new IntegrationDriver(
            $integration,
            $this->requestBuilder,
            $this->httpClient
        );
    }
}