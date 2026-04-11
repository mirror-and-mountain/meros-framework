<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Blade;

use MM\Meros\App\Framework;
use MM\Meros\App\Context;
use MM\Meros\App\Support\ClassInfo;

use MM\Meros\App\Listeners\MigrationEventSubscriber;

use MM\Meros\Scripts\InstallCommands;
use MM\Meros\Scripts\UninstallCommands;

class FrameworkServiceProvider extends ServiceProvider {
    
    final public function register(): void {
        // Register the registry service provider
        $this->app->register(RegistryServiceProvider::class);

        // Register the context class
        $this->app->singleton(Context::class, function () {
            return new Context();
        });

        // Alias the context class (used in Context Facade)
        $this->app->alias(Context::class, 'meros.context');

        // Register the Framework class as a singleton in the service container
        $this->app->singleton(Framework::class, function ($app) {
            return new Framework($app->make('meros.registry'));
        });
        
        // Alias the Framework class (used in Framework Facade)
        $this->app->alias(Framework::class, 'meros.framework');
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