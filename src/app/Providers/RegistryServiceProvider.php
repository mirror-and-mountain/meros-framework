<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\App\FeatureRegistry;

class RegistryServiceProvider extends ServiceProvider {
    
    final public function register(): void {
        $this->app->singleton(FeatureRegistry::class, function () {
            return new FeatureRegistry();
        });
    }   

    final public function boot(): void {
        // Nothing to do...
    }
}