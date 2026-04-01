<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use MM\Meros\App\Registry;

class RegistryServiceProvider extends ServiceProvider {
    
    final public function register(): void {
        $this->app->singleton('meros.registry', function () {
            return new Registry();
        });
    }   

    final public function boot(): void {
        // Nothing to do...
    }
}