<?php

namespace MM\Meros\Helpers;

use Illuminate\Support\Facades\Blade;

class Livewire
{
    /**
     * Checks whether Livewire assets have already been injected
     * and injects them if they haven't.
     */
    public static function injectAssets(bool $admin = false): bool
    {
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

        return true;
    }
}
