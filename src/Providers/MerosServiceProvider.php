<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

use MM\Meros\Contracts\ThemeManager;

use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\ExtensionLoader;

class MerosServiceProvider extends ServiceProvider
{
    private bool    $registered = false;

    public function register(): void
    {
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get( $themeClass );

        if ( $themeClass->extends(ThemeManager::class) ) {
            $this->app->singleton(
                'meros.theme_manager', fn($app) => new $themeClass( $app )
            );
            $this->registered = true;
        }

        defined('MEROS') || define('MEROS', true);
    }

    public function boot(): void
    {
        $this->ensureAppKey();

        if ( $this->registered ) {
            $theme  = $this->app->make('meros.theme_manager');
            $loader = ExtensionLoader::init( $theme );

            $loader->loadExtensions('extensions');
            $loader->loadExtensions('plugins');
            $loader->loadExtensions('features');

            do_action('meros_theme_add_features', $theme);

            $theme->initialise();
        }
    }

    private function ensureAppKey(): void
    {
        $envPath = base_path('.env');
        $key = 'base64:' . base64_encode(random_bytes(32));
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
