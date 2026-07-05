<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\Support\Integrations\RequestBuilder;
use MM\Meros\Support\Integrations\AuthResolver;
use MM\Meros\Support\Integrations\HttpClient;
use MM\Meros\Support\Integrations\CRM\SyncValueResolver;
use MM\Meros\Support\Integrations\CRM\SyncJobRunner;

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

        $this->app->singleton(SyncValueResolver::class, function () {
            return new SyncValueResolver();
        });

        $this->app->singleton(SyncJobRunner::class, function ($app) {
            return new SyncJobRunner(
                $app->make(SyncValueResolver::class)
            );
        });
    }

    public function boot(): void {
        //
    }
}