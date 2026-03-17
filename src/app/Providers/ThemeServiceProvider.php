<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Services\Theme\ThemeManager;
use MM\Meros\App\Facades\Theme;

use MM\Meros\App\Helpers\ClassInfo;
use MM\Meros\App\Helpers\BootTasks;
use MM\Meros\App\Helpers\ActivationTasks;
use MM\Meros\App\Helpers\DeactivationTasks;

use MM\Meros\Scripts\MakeCommands;
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
            // Do theme ready action
            do_action('meros_theme_ready', Theme::getInstance());

            // Initalise packages
            foreach ($this->app->tagged('meros.theme.package') as $package) {
                $package->initialise();
            }

            // Initialise theme.
            Theme::initialise();

            // Register theme activation and deactivation hooks.
            $this->registerThemeActivationHooks();

            // Inject Livewire assets
            BootTasks::injectLivewireAssets();

            // Load framework views for components
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'meros');
        }

        // Enable wp meros cli if appropriate
        if ($this->app->runningInConsole()) {
            if (defined('WP_CLI') && \WP_CLI) {
                $makeCli         = new MakeCommands();
                $environmentsCli = new EnvironmentCommands();
                $migrationsCli   = new MigrationCommands();
                
                \WP_CLI::add_command('meros:env', $environmentsCli);
                \WP_CLI::add_command('meros:migration', $migrationsCli);
                \WP_CLI::add_command('meros:make', $makeCli);
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
            $this->app->singleton('meros.theme', $themeClass->name);
            $this->registered = true;
        }

        defined('MEROS') || define('MEROS', true);
    }

    /**
     * Registers packages as singletons in the service container. 
     * Checks packages extend the correct base class before registering.
     * 
     * @return void
     */
    private function registerPackages(): void {
        $packages = Config::get("theme.packages") ?? [];;
        foreach ($packages as $serviceProvider) {
            $providerClass = ClassInfo::get($serviceProvider);
            if ($providerClass->extends(PackageServiceProvider::class)) {
                $this->app->register($providerClass->name);
            }
        }
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
