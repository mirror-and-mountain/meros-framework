<?php

namespace MM\Meros\Helpers;

use Livewire\Livewire as LivewireFacade;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

class Livewire {
    /**
     * Injects Livewire's styles and scripts into the WP head and footer.
     *
     * @param boolean $admin
     * @return boolean
     */
    public static function injectAssets(bool $admin = false): bool {
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

    /**
     * Sets Livewire's script route to the assets directory inside the theme.
     *
     * @return void
     */
    public static function setScriptRoute(): void {
        LivewireFacade::setScriptRoute(function () {
            return Route::get(get_theme_file_uri('assets/livewire/livewire.min.js'));
        });
    }

    /**
     * Ensures that an APP_KEY exists in the theme's .env file.
     *
     * @return void
     */
    public static function ensureAppKey(): void {
        $envPath = base_path('.env');
        $key     = 'base64:' . base64_encode(random_bytes(32));
        $comment = "# An App Key is required for Livewire functionality";

        if (!file_exists($envPath)) {
            $envContent = "{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
            return;
        }

        $envContent = file_get_contents($envPath);

        if (!preg_match('/^APP_KEY=.*$/m', $envContent)) {
            $envContent = rtrim($envContent) . "\n\n{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
        }
    }
}
