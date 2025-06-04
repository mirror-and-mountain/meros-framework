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
    public static function injectAssets( bool $admin = false ): void
    {
        $theme       = app()->make('meros.theme_manager');

        if ( !$admin ) {
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

        } else {
            $initialised = $theme->livewireInitialisedAdmin;

            if ( $initialised ) {
                return;
            }

            // Add Livewire styles to the admin head
            add_action('admin_head', function () {
                echo Blade::render('@livewireStyles');
            });

            // Add Livewire scripts to the admin footer
            add_action('admin_footer', function () {
                echo Blade::render('@livewireScripts');
            });

            $theme->livewireInitialisedAdmin = true;
        }
    }
}