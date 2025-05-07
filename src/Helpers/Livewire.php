<?php 

namespace MM\Meros\Helpers;

use MM\Meros\Facades\Theme;
use Illuminate\Support\Facades\Blade;

class Livewire
{
    public static function injectAssets(): void
    {
        $initialised = Theme::$livewireInitialised;

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

        Theme::$livewireInitialised = true;
    }
}