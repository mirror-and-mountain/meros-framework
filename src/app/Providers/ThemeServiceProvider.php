<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Services\Theme\ThemeManager;
use MM\Meros\App\Services\Theme\AdminManager;

use MM\Meros\App\Helpers\ClassInfo;
use MM\Meros\App\Helpers\BootTasks;
use MM\Meros\App\Helpers\ActivationTasks;
use MM\Meros\App\Helpers\DeactivationTasks;

use MM\Meros\Scripts\EnvironmentCommands;
use MM\Meros\Scripts\MigrationCommands;

class ThemeServiceProvider extends ServiceProvider {
    
    /** Indicates whether the theme manager has been registered.
     *
     * @var boolean
     */
    private bool $registered = false;

    /**
     * Retrieves the theme manager class from theme config and binds
     * it as a singleton.
     * 
     * @return void
     */
    public function register(): void {
        $this->registerTheme();

        if ($this->registered) {
            $this->registerAdminManager();
            $this->registerPackages();
        }
    }

    /**
     * Loads theme features before initialising them via the
     * theme manager class. Also sets up the WP CLI commands if
     * running in the console.
     * 
     * @return void
     */
    public function boot(): void {
        // Perform boot tasks.
        BootTasks::setScriptRoute();

        if ($this->registered) {
            // Get theme manager instance.
            $theme = $this->app->make('meros.theme');

            // Do theme ready action
            do_action('meros_theme_ready', $theme);
            
            // Runs after registered features have been loaded, but before they are initialised.
            $themeSlug = $theme->getThemeSlug();
            do_action("{$themeSlug}_add_features", $theme);

            // Initialise registered features and extensions.
            $theme->initialise();

            // Register theme activation and deactivation hooks.
            $this->registerThemeActivationHooks();

            // Admin tasks
            if (is_admin()) {
                // Inject Livewire assets into the admin area
                BootTasks::injectLivewireAssets(true);
                // Initialise admin
                $this->initialiseAdmin();
            } else {
                // Inject Livewire assets into the frontend
                BootTasks::injectLivewireAssets(false);
            }
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
     * Registers the theme manager class as a singleton in the service container.
     * The theme manager class is specified in the theme config file located at
     * config/theme.php. Also defines the MEROS constant if it is not
     * already defined.
     * 
     * @return void
     */
    private function registerTheme(): void {      
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get($themeClass);

        if ($themeClass->extends(ThemeManager::class)) {
            $this->app->singleton('meros.theme', function ($app) use ($themeClass) {
                return new ($themeClass->name)($app);
            });

            $this->app->alias('meros.theme', ThemeManager::class);
            $this->registered = true;
        }

        defined('MEROS') || define('MEROS', true);
    }

    /**
     * Registers the admin manager class as a singleton in the service container.
     * 
     * @return void
     */
    private function registerAdminManager(): void {
        $this->app->singleton('meros.admin', function ($app) {
            return new AdminManager()($app);
        });

        $this->app->alias('meros.admin', AdminManager::class);
    }

    /**
     * Registers packages as singletons in the service container. 
     * Checks packages extend the correct base class before registering.
     * 
     * @return void
     */
    private function registerPackages(): void {
        $packages = Config::get("theme.packages") ?? [];

        foreach ($packages as $serviceProvider) {
            $providerClass = ClassInfo::get($serviceProvider);
            dd($providerClass);
            if ($providerClass->extends(PackageServiceProvider::class)) {
                $this->app->register($providerClass->name);
            }
        }
    }

    /**
     * Adds default options pages to WP Admin &
     * initialises the Admin Manager service.
     *
     * @see boot() method of this service provider.
     * @return void
     */
    private function initialiseAdmin(): void {
        $adminManager = $this->app->make('meros.admin');
        $adminConfig  = include_once __DIR__ . '/../../config/admin.php';
        $defaultOptionsPages = $adminConfig['options_pages'] ?? [];
        
        foreach ($defaultOptionsPages as $slug => $config) {
            $adminManager->registerOptionsPage($slug, $config);
        }
        
        $adminManager->initialise();
    }

    /**
     * Registers hooks related to theme activation and deactivation.
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
     * @see boot() method of this service provider.
     * @return void
     */
    public function afterSwitchTheme(): void {
        // Get theme manager instance.
        $themeInstance = $this->app->make('meros.theme');

        // Run activation tasks.
        ActivationTasks::clearSessionFiles();
        ActivationTasks::ensureAppKey();
        ActivationTasks::ensurePrettyPermalinks();
        ActivationTasks::runCoreMigrations($themeInstance);
    }

    /**
     * Handles tasks that need to be performed when the theme is switched away from Meros.
     * 
     * @see boot() method of this service provider.
     * @return void
     */
    public function switchTheme(): void {
        // Get theme manager instance.
        $themeInstance = $this->app->make('meros.theme');
        
        // Run deactivation tasks.
        DeactivationTasks::clearSessionFiles();
        DeactivationTasks::removeSettings($themeInstance);
        DeactivationTasks::reverseCoreMigrations($themeInstance);
    }
}
