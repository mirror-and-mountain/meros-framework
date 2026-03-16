<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\App\Services\Theme\AdminManager;
use MM\Meros\App\Facades\Admin;

class AdminServiceProvider extends ServiceProvider {
    
    /**
     * Registers the admin manager class as a singleton in the service container.
     *
     * @return void
     */
    public function register(): void {
        $this->app->singleton('meros.admin', AdminManager::class);
    }

    /**
     * Initialises the admin manager if in the admin area and injects Livewire assets.
     *
     * @return void
     */
    public function boot(): void {
        if (is_admin()) {
            // Initialise admin
            Admin::initialise();
        }
    }
}