<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Component;

use MM\Meros\Contracts\ThemeManager;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\Loader;
use MM\Meros\Helpers\Livewire as LivewireHelper;

use MM\Meros\Scripts\EnvironmentCommands;
use MM\Meros\Scripts\MigrationCommands;

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
        LivewireHelper::setScriptRoute();

        if ($this->registered) {
            // Get theme manager instance.
            $theme = $this->app->make('meros.theme_manager');
            
            // Load features and extensions
            $loader = Loader::init($theme);
            $loader->load('extensions');
            $loader->load('features');

            // Runs after registered features have been loaded, but before they are initialised.
            $themeSlug = $theme->getThemeSlug();
            do_action("{$themeSlug}_add_features", $theme);

            // Initialise registered features and extensions.
            $theme->initialise();

            // Register theme activation and deactivation hooks.
            $this->registerThemeActivationHooks();

            // Load portal resources
            $this->loadPortalResources();
        }

        // Enable wp meros cli if appropriate
        if ($this->app->runningInConsole()) {
            if (defined('WP_CLI') && \WP_CLI) {
                $environmentsCli = new EnvironmentCommands();
                $migrationsCli = new MigrationCommands();
                \WP_CLI::add_command('meros:env', $environmentsCli);
                \WP_CLI::add_command('meros:migration', $migrationsCli);
            }
        }
    }

    /**
     * Registers hooks related to theme activation and deactivation.
     * 
     * This method is called in the boot method of this service provider.
     * 
     * @see boot() method of this service provider.
     * @return void
     */
    private function registerThemeActivationHooks(): void {
        add_action('after_switch_theme', [$this, 'afterSwitchTheme']);
        add_action('switch_theme', [$this, 'switchTheme']);
    }

    /**
     * Handles tasks that need to be performed after the theme is switched to Meros.
     * 
     * This method is hooked into the 'after_switch_theme' action in Wordpress
     * via the add_action call in the boot method of this service provider.
     * 
     * @see boot() method of this service provider.
     * @return void
     */
    public function afterSwitchTheme(): void {
        // Get theme manager instance.
        $themeInstance = $this->app->make('meros.theme_manager');
        
        // Clear session files.
        $sessionDir = get_theme_file_path('storage/framework/sessions');

        if (is_dir($sessionDir)) {
            $files = glob($sessionDir . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Ensure an APP_KEY exists for Livewire.
        LivewireHelper::ensureAppKey();

        // Ensure pretty permalinks are set.
        $themeInstance->ensurePrettyPermalinks();

        // Run meros core database migrations.
        if (
            $themeInstance->allowsMigrations() !== false &&
            $themeInstance->onlyAllowsMigrationsFromCli() === false
        ) {
            $themeInstance->setMerosCoreMigrations();
            $themeInstance->runMigrations('meros_core');
        }
    }

    /**
     * Handles tasks that need to be performed when the theme is switched away from Meros.
     * 
     * This method is hooked into the 'switch_theme' action in Wordpress
     * via the add_action call in the boot method of this service provider.
     * 
     * @see boot() method of this service provider.
     * @return void
     */
    public function switchTheme(): void {
        // Get theme manager instance.
        $themeInstance = $this->app->make('meros.theme_manager');
        // Unregister theme settings.
        $settings = $themeInstance->getRegisteredSettings();
        foreach ($settings as $_ => $optionGroups) {
            foreach ($optionGroups as $optionGroup => $options) {
                foreach ($options as $optionName => $_) {
                    unregister_setting($optionGroup, $optionName);
                    delete_option($optionName);
                }
            }
        }

        // Drop migrated tables if the theme allows database migrations.
        if ($themeInstance->allowsMigrations() !== false) {
            $themeInstance->setMerosCoreMigrations();
            $themeInstance->rollbackMigrations();
        }
    }

    public function loadPortalResources(): void {
        $portalClass = ClassInfo::getFromPath(__DIR__ . '/../Components/Portal.php');
        
        if ($portalClass->extends(Component::class)) {
            Livewire::component('meros.portal', $portalClass->name);
        }


        $this->loadRoutesFrom(__DIR__ . '/../routes/portal.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'meros');
    }
}
