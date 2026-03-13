<?php

namespace MM\Meros\Services\Integrations;

use MM\Meros\Models\Integration;
use MM\Meros\Services\Integrations\IntegrationDriver;

class IntegrationManager {
    protected RequestBuilder $requestBuilder;

    protected HttpClient $httpClient;

    /**
     * Registered driver classes keyed by integration slug.
     */
    protected static array $drivers = [];

    public function __construct(
        RequestBuilder $requestBuilder,
        HttpClient $httpClient
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->httpClient = $httpClient;
    }

    /**
     * Static entrypoint for resolving drivers.
     */
    public static function driver(string $slug): IntegrationDriver {
        return app()->make(self::class)->resolveDriver($slug);
    }

    /**
     * Allow packages to register custom drivers.
     */
    public static function registerDriver(string $slug, string $driverClass): void {
        self::$drivers[$slug] = $driverClass;
    }

    /**
     * Resolve the correct driver instance.
     */
    protected function resolveDriver(string $slug): IntegrationDriver {
        $integration = Integration::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $driverClass = self::$drivers[$slug] ?? IntegrationDriver::class;

        return app()->make($driverClass, [
            'integration' => $integration,
            'requestBuilder' => $this->requestBuilder,
            'httpClient' => $this->httpClient,
        ]);
    }
}