<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use MM\Meros\App\Facades\Admin;
use MM\Meros\App\Admin\AdminManager;

class AdminServiceProvider extends ServiceProvider {
    
    /**
     * Registers the admin manager class as a singleton in the service container.
     *
     * @return void
     */
    public function register(): void {
        $this->app->singleton(AdminManager::class, AdminManager::class);
        $this->app->alias(AdminManager::class, 'meros.admin');
    }

    /**
     * Initialises the admin manager if in the admin area and injects Livewire assets.
     *
     * @return void
     */
    public function boot(): void {
        $this->app->booted(function () {
            if (is_admin()) {
                // Initialise admin
                Admin::initialise();
            }
        });

    }
}