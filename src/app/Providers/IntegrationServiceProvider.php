<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\Support\Integrations\RequestBuilder;
use MM\Meros\Support\Integrations\AuthResolver;
use MM\Meros\Support\Integrations\HttpClient;

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
    }

    public function boot(): void {
        //
    }
}