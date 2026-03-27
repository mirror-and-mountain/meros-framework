<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Framework;
use MM\Meros\App\FeatureRegistry;
use MM\Meros\App\Context;
use MM\Meros\App\Support\ClassInfo;

use MM\Meros\Scripts\MakeCommands;
use MM\Meros\Scripts\EnvironmentCommands;
use MM\Meros\Scripts\MigrationCommands;

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
            return new Framework($app->make(FeatureRegistry::class));
        });
        
        // Alias the Framework class (used in Framework Facade)
        $this->app->alias(Framework::class, 'meros.framework');
    }

    final public function boot(): void {
        // Set context
        $this->app->make(Context::class);

        // Init the Framework class to trigger the constructor and set up the framework
        $this->app->make(Framework::class)->initialiseFramework();

        // Call the Theme Service Provider
        $this->app->register(ThemeServiceProvider::class);

        // Register packages
        $this->registerPackages();

        // dd($this->app->make(FeatureRegistry::class));

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
}