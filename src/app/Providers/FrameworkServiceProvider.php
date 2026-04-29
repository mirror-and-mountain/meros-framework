<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Blade;

use MM\Meros\App\Framework;
use MM\Meros\App\Context;

use MM\Meros\Support\ClassInfo;

use MM\Meros\Services\Registers\Assets;
use MM\Meros\Services\Registers\Blocks;
use MM\Meros\Services\Registers\Forms;
use MM\Meros\Services\Registers\Fields;
use MM\Meros\Services\Registers\FieldGroups;
use MM\Meros\Services\Registers\FieldStyles;
use MM\Meros\Services\Registers\MenuPages;
use MM\Meros\Services\Registers\Settings;
use MM\Meros\Services\Registers\SettingsFields;
use MM\Meros\Services\Registers\SettingsSections;

use MM\Meros\App\Listeners\MigrationEventSubscriber;

use MM\Meros\Scripts\InstallCommands;
use MM\Meros\Scripts\UninstallCommands;

class FrameworkServiceProvider extends ServiceProvider {
    
    /**
     * Registers the framework's services, including helper classes and the Framework class itself.
     *
     * @return void
     */
    final public function register(): void {
        $this->registerHelpers();
        $this->registerRegisters();
        $this->registerFramework();
    }

    final public function boot(): void {
        // Register event subscribers
        Event::subscribe(MigrationEventSubscriber::class);

        // Set context
        $this->app->make(Context::class);

        // Init the Framework class to trigger the constructor and set up the framework
        $framework = $this->app->make(Framework::class)->__initialise();
        
        // Load views from the framework's views directory
        $viewsPath = $framework->getPreference('views_path');
        $this->loadViewsFrom($viewsPath, 'meros');
        
        // Register the framework's components directory for anonymous components
        Blade::anonymousComponentPath($viewsPath . '/components');

        // Load routes from the framework's routes directory
        $routesPath = $framework->getPreference('routes_path');

        if (File::exists($routesPath) && File::isDirectory($routesPath)) {
            $routeFiles = File::files($routesPath);
            foreach ($routeFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $this->loadRoutesFrom($file->getPathname());
                }
            }
        }

        // Call the Theme Service Provider
        $this->app->register(ThemeServiceProvider::class);

        // Register packages
        $this->registerPackages();

        // dd($this->app->make('meros.registry'));

        // Enable wp meros cli if appropriate
        if ($this->app->runningInConsole()) {
            if (defined('WP_CLI') && \WP_CLI) {
                $installCli      = new InstallCommands();
                $uninstallCli    = new UninstallCommands();
                
                \WP_CLI::add_command('meros:install', $installCli);
                \WP_CLI::add_command('meros:uninstall', $uninstallCli);
            }

            if (getenv('MEROS_ENVIRONMENT') === 'true') {
                $environmentsCli = new \MM\Meros\Scripts\EnvironmentCommands();
                \WP_CLI::add_command('meros:env', $environmentsCli);
            }
        }
    }

    /**
     * Registers the framework's registers as singletons in the service container.
     * Each register is responsible for managing a specific type of feature (e.g. assets, blocks, fields).
     *
     * @return void
     */
    private function registerRegisters(): void {
        $this->app->singleton(Assets::class, function () {
            return new Assets();
        });

        $this->app->singleton(Blocks::class, function () {
            return new Blocks();
        });

        $this->app->singleton(Forms::class, function () {
            return new Forms();
        });

        $this->app->singleton(Fields::class, function () {
            return new Fields();
        });

        $this->app->singleton(FieldGroups::class, function () {
            return new FieldGroups();
        });

        $this->app->singleton(FieldStyles::class, function () {
            return new FieldStyles();
        });

        $this->app->singleton(MenuPages::class, function () {
            return new MenuPages();
        });

        $this->app->singleton(Settings::class, function () {
            return new Settings();
        });

        $this->app->singleton(SettingsFields::class, function () {
            return new SettingsFields();
        });

        $this->app->singleton(SettingsSections::class, function () {
            return new SettingsSections();
        });

        $this->app->alias(Assets::class, 'meros.registers.assets');
        $this->app->alias(Blocks::class, 'meros.registers.blocks');
        $this->app->alias(Forms::class, 'meros.registers.forms');
        $this->app->alias(Fields::class, 'meros.registers.fields');
        $this->app->alias(FieldGroups::class, 'meros.registers.field_groups');
        $this->app->alias(FieldStyles::class, 'meros.registers.field_styles');
        $this->app->alias(MenuPages::class, 'meros.registers.menu_pages');
        $this->app->alias(Settings::class, 'meros.registers.settings');
        $this->app->alias(SettingsFields::class, 'meros.registers.settings_fields');
        $this->app->alias(SettingsSections::class, 'meros.registers.settings_sections');
    }

    /**
     * Registers helper classes as singletons in the service container 
     * and aliases them for use in Facades.
     *
     * @return void
     */
    private function registerHelpers(): void {
        // Register the context class
        $this->app->singleton(Context::class, function () {
            return new Context();
        });

        // Alias the context class (used in Context Facade)
        $this->app->alias(Context::class, 'meros.context');
    }

    /**
     * Registers the Framework class as a singleton in the service container 
     * and aliases it for use in the Framework Facade.
     *
     * @return void
     */
    private function registerFramework(): void {
        $this->app->singleton(Framework::class, function () {
            return new Framework();
        });

        $this->app->alias(Framework::class, 'meros.framework');
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
            if ($providerClass->extends(PackageServiceProvider::class)) {
                $this->app->register($providerClass->name);
            }
        }
    }
}