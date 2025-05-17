<?php 

namespace MM\Meros\Helpers;

use Illuminate\Support\Facades\Blade;

class Livewire
{
    /**
     * Checks whether Livewire assets have already been injected
     * and injects them if they haven't.
     *
     * @return void
     */
    public static function injectAssets(): void
    {
        $theme       = app()->make('meros.theme_manager');
        $initialised = $theme->livewireInitialised;

        if ( $initialised ) {
            return;
        }

         // Add Livewire styles to the head
         add_action('wp_head', function () {
            echo Blade::render('@livewireStyles');
        });

        // Add Livewire scripts to the footer
        add_action('wp_footer', function () {
            echo Blade::render('@livewireScripts');
        });

        $theme->livewireInitialised = true;
    }
}