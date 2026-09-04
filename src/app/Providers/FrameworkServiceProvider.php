<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\MerosServiceProvider;

use MM\Meros\App\Framework;
use MM\Meros\App\Listeners\MigrationEventSubscriber;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;

use MM\Meros\Support\ClassInfo;
use MM\Meros\Support\Registers;
use MM\Meros\Support\Context;
use MM\Meros\Support\ViteAssets;

use MM\Meros\Registers\Packages;

use MM\Meros\Registers\Admin\Pages;
use MM\Meros\Registers\Admin\Settings;
use MM\Meros\Registers\Admin\SettingsSections;
use MM\Meros\Registers\Admin\SettingsContainers;

use MM\Meros\Registers\Components\Blocks;
use MM\Meros\Registers\Components\Forms;
use MM\Meros\Registers\Components\Fields;
use MM\Meros\Registers\Components\FieldGroups;

use MM\Meros\Registers\Content\PostTypes;

use MM\Meros\Registers\Data\PostMetaContainers;
use MM\Meros\Registers\Data\PostMetaDefinitions;
use MM\Meros\Registers\Data\Tables;
use MM\Meros\Registers\Data\Integrations;

use MM\Meros\Registers\Assets\AssetGroups;
use MM\Meros\Registers\Assets\Assets;

use MM\Meros\Scripts\InstallCommands;
use MM\Meros\Scripts\UninstallCommands;

use MM\Meros\Facades\Theme as ThemeFacade;
use MM\Meros\Facades\Packages as PackagesFacade;
use MM\Meros\Facades\Framework as FrameworkFacade;

class FrameworkServiceProvider extends MerosServiceProvider {
    /**
     * An array of helper classes to be registered as singletons in the service container.
     *
     * @var array
     */
    private array $helpers = [
        Context::class,
        Registers::class
    ];

    /**
     * An array of register classes to be registered as singletons in the service container.
     *
     * @var array
     */
    private array $registers = [
        Packages::class,
        Pages::class,
        Settings::class,
        SettingsSections::class,
        SettingsContainers::class,

        Blocks::class,
        Forms::class,
        Fields::class,
        FieldGroups::class,

        PostTypes::class,

        PostMetaContainers::class,
        PostMetaDefinitions::class,
        Tables::class,
        Integrations::class,

        AssetGroups::class,
        Assets::class
    ];

    // =========================================================================
    // Registration
    // =========================================================================
    
    /**
     * Registers the framework's services.
     *
     * @return void
     */
    final public function register(): void {
        $this->initHelpers();
        $this->initRegisters();
        $this->registerFramework();
    }

    /**
     * Registers helper classes as singletons in the service container 
     * and aliases them for use in Facades.
     *
     * @return void
     */
    private function initHelpers(): void {
        foreach ($this->helpers as $helperClass) {
            $this->app->singleton($helperClass, function () use ($helperClass) {
                return new $helperClass();
            });

            $this->app->alias($helperClass, 'meros.helpers.' . strtolower(Str::snake(class_basename($helperClass))));
            $this->app->make($helperClass);
        }

        $this->registerViteDirective();
    }

    /**
     * Registers the @viteAssets Blade directive for including Vite assets in views.
     *
     * @return void
     */
    private function registerViteDirective(): void {
        Blade::directive('viteAssets', function (?string $context = null, ?string $entry = null) {
            $context  = $context ?: 'theme';
            $context  = trim($context, '\'"'); // Remove quotes if present
            $vitePath = null;
            $instance = null;

            $isPackageContext = !in_array($context, ['theme', 'framework']);

            if ($isPackageContext) {
                $instance = PackagesFacade::all()->firstWhere(function ($package) use ($context) {
                    return $package->getHandle() === $context;
                });
            }

            else if ($context === 'theme') {
                $instance  = ThemeFacade::get();
            } 
            
            else if ($context === 'framework') {
                $instance  = FrameworkFacade::get();
            }

            if (!$instance) {
                return "<!-- Vite Assets: No instance found for context '{$context}' -->";
            }

            $vitePath  = $instance->getPreference('vite_assets_path');
            $srcPath   = trailingslashit($vitePath) . 'src';
            $buildPath = trailingslashit($vitePath) . 'build';
            $entry     = trailingslashit($srcPath)  . ($entry ?: 'index.js');

            $entryExists     = $entry && File::exists($entry);
            $buildPathExists = $buildPath && File::exists($buildPath) && File::isDirectory($buildPath);
        
            if (!$entryExists || !$buildPathExists) {
                return "<!-- Vite Assets: No entry or build path defined for context '{$context}' -->";
            }

            return $this->app->make(ViteAssets::class)->render($entry, $buildPath);
        });
    }

    /**
     * Registers the framework's registers as singletons in the service container.
     * Each register is responsible for managing a specific type of feature (e.g. assets, blocks, fields).
     *
     * @return void
     */
    private function initRegisters(): void {
        foreach ($this->registers as $registerClass) {
            $this->app->singleton($registerClass, function () use ($registerClass) {
                return new $registerClass();
            });

            $this->app->alias($registerClass, 'meros.registers.' . strtolower(Str::snake(class_basename($registerClass))));
            $this->app->make($registerClass);
        }
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

    // =========================================================================
    // Booting
    // =========================================================================

    /**
     * Boots the framework's services.
     *
     * @return void
     */
    final public function boot(): void {
        $this->initEventSubscribers();

        // Instantiate the framework
        $framework = $this->app->make(Framework::class)->get();

        // Initialise the framework
        $this->registerLivewireComponents($framework, 'meros');
        $this->initHooks();
        $this->registerViews($framework, 'meros');
        $this->registerRoutes($framework);

        $framework->configure();
        $framework->whenConfigured();
        do_action('meros_framework_configured', $framework);

        // Register packages
        $this->registerPackages();
        do_action('meros_packages_registered', PackagesFacade::all());

        // Register the theme
        $this->app->register(ThemeServiceProvider::class);
        do_action('meros_theme_registered', ThemeFacade::get());

        // Enable wp meros cli if appropriate
        if ($this->app->runningInConsole()) {
            $this->initMerosCli();
        }

        // Fire the framework booted action when the service container is ready.
        $this->app->booted(function () {
            do_action('meros_framework_booted');
        });
    }

    /**
     * Initialises event subscribers for the framework.
     *
     * @return void
     */
    private function initEventSubscribers(): void {
        Event::subscribe(MigrationEventSubscriber::class);
    }

    /**
     * Registers wp head and footer hooks required by the framework.
     *
     * @return void
     */
    private function initHooks(): void {
        // Hook Livewire into WP header and footer
        add_action('wp_head', function () {
            echo Blade::render('@livewireStyles');
        });

        add_action('admin_head', function () {
            echo Blade::render('@livewireStyles');
        });

        add_action('wp_footer', function () {
            echo Blade::render('@livewireScripts');
        });

        add_action('admin_footer', function () {
            echo Blade::render('@livewireScripts');
        });
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

    /**
     * Initialises the Meros CLI commands if WP-CLI is available and the 
     * environment variable MEROS_ENVIRONMENT is set to true.
     *
     * @return void
     */
    private function initMerosCli(): void {
        if (defined('WP_CLI') && class_exists('WP_CLI') && \WP_CLI) {
            $installCli   = new InstallCommands();
            $uninstallCli = new UninstallCommands();
            
            \WP_CLI::add_command('meros:install', $installCli);
            \WP_CLI::add_command('meros:uninstall', $uninstallCli);

            if (getenv('MEROS_ENVIRONMENT') === 'true') {
                $environmentsCli = new \MM\Meros\Scripts\EnvironmentCommands();
                \WP_CLI::add_command('meros:env', $environmentsCli);
            }
        }
    }
}