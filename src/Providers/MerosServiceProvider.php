<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

use MM\Meros\Contracts\ThemeManager;

use MM\Meros\Helpers\Loader;
use MM\Meros\Helpers\ClassInfo;

use MM\Meros\Scripts\MerosCommands;

use Livewire\Livewire;
use Illuminate\Support\Facades\Route;

/**
 * Binds Meros contracts to the Laravel application.
 */
class MerosServiceProvider extends ServiceProvider
{
    private bool $registered = false;

    /**
     * Retrieves the theme manager class from theme config and binds
     * it as a singleton.
     *
     * @return void
     */
    public function register(): void
    {
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get( $themeClass );

        if ( $themeClass->extends(ThemeManager::class) ) {
            $this->app->singleton(
                'meros.theme_manager', fn($app) => new $themeClass->name( $app )
            );
            $this->registered = true;
        }

        defined('MEROS') || define('MEROS', true);
    }

    /**
     * Loads theme features, extensions and plugins before
     * initialising them via the the theme manager class.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureLivewire();
        
        if ( $this->registered ) {
            $theme  = $this->app->make('meros.theme_manager');
            $loader = Loader::init( $theme );

            $loader->load('extensions');
            $loader->load('plugins');
            $loader->load('features');

            $themeSlug = $theme->getThemeSlug();
            do_action("{$themeSlug}_add_features", $theme);

            $theme->initialise();
        }

        // Enable wp meros cli if appropriate
        if ( $this->app->runningInConsole() ) {
            if ( defined( 'WP_CLI' ) && \WP_CLI ) {
                $merosCli = new MerosCommands();
                \WP_CLI::add_command( 'meros', $merosCli );
            }
        }
    }

    /**
     * Configures Livewire settings for the theme.
     *
     * @return void
     */
    private function configureLivewire(): void
    {
        // Make sure an application key exists.
        $this->ensureAppKey();
        // Set the livewire script route to use theme assets dir.
        Livewire::setScriptRoute(function ($handle) {
            return Route::get(get_theme_file_uri('assets/livewire/livewire.min.js'), $handle);
        });
    }

    /**
     * Ensures that a .env file with an APP_KEY exists for Livewire
     * functionality.
     *
     * @return void
     */
    private function ensureAppKey(): void
    {
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
