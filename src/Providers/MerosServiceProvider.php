<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

use MM\Meros\Contracts\ThemeManager;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\Loader;
use MM\Meros\Helpers\Livewire;
use MM\Meros\Scripts\MerosCommands;

class MerosServiceProvider extends ServiceProvider {
    private bool $registered = false;

    /**
     * Retrieves the theme manager class from theme config and binds
     * it as a singleton.
     * 
     * @return void
     */
    public function register(): void {
        $themeClass = Config::get('features.theme_class');
        $themeClass = ClassInfo::get($themeClass);

        if ($themeClass->extends(ThemeManager::class)) {
            // $this->app->singleton(
            //     'meros.theme_manager', fn ($app) => new $themeClass->name($app)
            // );

            $this->app->singleton('meros.theme_manager', function ($app) use ($themeClass) {
                return new ($themeClass->name)($app);
            });

            $this->registered = true;
        }

        defined('MEROS') || define('MEROS', true);
    }

    /**
     * Loads theme features before initialising them via the
     * theme manager class. Also sets up the WP CLI commands if
     * running in the console.
     * 
     * @return void
     */
    public function boot(): void {
        // Setup Livewire
        Livewire::setScriptRoute();

        if ($this->registered) {
            $theme = $this->app->make('meros.theme_manager');
            $loader = Loader::init($theme);

            $loader->load('extensions');
            $loader->load('features');

            $themeSlug = $theme->getThemeSlug();
            do_action("{$themeSlug}_add_features", $theme);

            $theme->initialise();
        }

        // Enable wp meros cli if appropriate
        if ($this->app->runningInConsole()) {
            if (defined('WP_CLI') && \WP_CLI) {
                $merosCli = new MerosCommands;
                \WP_CLI::add_command('meros', $merosCli);
            }
        }
    }
}
