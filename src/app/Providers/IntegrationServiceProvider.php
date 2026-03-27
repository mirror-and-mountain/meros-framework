<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\App\Integrations\IntegrationManager;
use MM\Meros\App\Integrations\RequestBuilder;
use MM\Meros\App\Integrations\Auth\AuthResolver;
use MM\Meros\App\Integrations\Http\HttpClient;

class IntegrationServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->singleton(HttpClient::class, function () {
            return new HttpClient();
        });

        $this->app->singleton(AuthResolver::class, function () {
            return new AuthResolver();
        });

        $this->app->singleton(RequestBuilder::class, function ($app) {
            return new RequestBuilder(
                $app->make(AuthResolver::class)
            );
        });

        $this->app->singleton(IntegrationManager::class, function ($app) {
            return new IntegrationManager(
                $app->make(RequestBuilder::class),
                $app->make(HttpClient::class)
            );
        });
    }

    public function boot(): void {
        //
    }
}