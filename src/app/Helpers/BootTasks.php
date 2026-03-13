<?php 

namespace MM\Meros\App\Helpers;

use Livewire\Livewire;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;

class BootTasks {
    /**
     * Sets Livewire's script route to the assets directory inside the theme.
     *
     * @return void
     */
    public static function setScriptRoute(): void {
        Livewire::setScriptRoute(function () {
            return Route::get(get_theme_file_uri('assets/livewire/livewire.min.js'));
        });
    }

    /**
     * Injects Livewire's styles and scripts into the WP head and footer.
     *
     * @param boolean $admin
     * @return void
     */
    public static function injectLivewireAssets(bool $admin = false): void {
        $styleHook = $admin ? 'admin_head' : 'wp_head';
        $scriptHook = $admin ? 'admin_footer' : 'wp_footer';

        // Add Livewire styles to the admin head
        add_action($styleHook, function () {
            echo Blade::render('@livewireStyles');
        });

        // Add Livewire scripts to the admin footer
        add_action($scriptHook, function () {
            echo Blade::render('@livewireScripts');
        });
    }
}