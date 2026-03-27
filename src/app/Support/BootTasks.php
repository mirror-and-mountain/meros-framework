<?php 

namespace MM\Meros\App\Support;

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
        Livewire::setScriptRoute(function ($handle, $path) {
            return Route::get(get_theme_file_uri( 'assets/livewire/livewire.min.js'), $handle);
        });
    }

    /**
     * Injects Livewire's styles and scripts into the WP head and footer.
     *
     * @return void
     */
    public static function injectLivewireAssets(): void {
        add_action('wp_head', function () {
            echo Blade::render('@livewireStyles');
        });

        add_action('wp_footer', function () {
            echo Blade::render('@livewireScripts');
        });
    }
}