<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Framework;
use MM\Meros\App\Context;

use MM\Meros\Support\ClassInfo;
use MM\Meros\Services\Controllers\InstallerController;
use MM\Meros\Services\Controllers\IntegrationsController;
use MM\Meros\Services\Controllers\RestController;

use MM\Meros\Services\Registers\Assets;
use MM\Meros\Services\Registers\AssetGroups;
use MM\Meros\Services\Registers\Blocks;
use MM\Meros\Services\Registers\DynamicChoiceSources;
use MM\Meros\Services\Registers\Integrations;

use MM\Meros\Services\Registers\Forms;
use MM\Meros\Services\Registers\FormRows;
use MM\Meros\Services\Registers\Fields;
use MM\Meros\Services\Registers\FieldGroups;
use MM\Meros\Services\Registers\FormActions;
use MM\Meros\Services\Registers\FieldWrappers;

use MM\Meros\Services\Registers\Tables;
use MM\Meros\Services\Registers\MenuPages;
use MM\Meros\Services\Registers\MenuPageTemplates;
use MM\Meros\Services\Registers\Packages as PackagesRegister;
use MM\Meros\Services\Registers\PostTypes;
use MM\Meros\Services\Registers\PostMetaDefinitions;
use MM\Meros\Services\Registers\UserMetaDefinitions;
use MM\Meros\Services\Registers\Settings;
use MM\Meros\Services\Registers\SettingsFields;
use MM\Meros\Services\Registers\SettingsSections;

use MM\Meros\App\Listeners\MigrationEventSubscriber;

use MM\Meros\Support\ViteAssets;

use MM\Meros\Scripts\InstallCommands;
use MM\Meros\Scripts\UninstallCommands;

use MM\Meros\Facades\Theme;
use MM\Meros\Facades\Packages;
use MM\Meros\Facades\Framework as FrameworkAccessor;

use Livewire\Livewire;

class FrameworkServiceProvider extends ServiceProvider {

    use Concerns\HasViews, Concerns\HasRoutes, Concerns\HasLivewire;
    
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
        $framework = $this->app->make(Framework::class)->__initialise($this);

        // Register Livewire components
        $this->registerLivewireComponents($framework, 'meros');

        Livewire::addNamespace(
            namespace: 'toolbox',
            classNamespace: 'MM\\Meros\\App\\Toolbox',
            classPath: $framework->getPath('app/Toolbox'),
            classViewPath: $framework->getPath('resources/views/toolbox')
        );

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
        
        // Load views from the framework's views directory
        $this->registerViews($framework, 'meros');

        // Load routes from the framework's routes directory
        $this->registerRoutes($framework);

        // Call the Theme Service Provider
        $this->app->register(ThemeServiceProvider::class);

        // Register packages
        $this->registerPackages();

        // Trigger an action after all providers have been registered
        do_action('meros_providers_registered', Theme::get(), Packages::all());

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
        $this->app->singleton(AssetGroups::class, function () {
            return new AssetGroups();
        });

        $this->app->singleton(Assets::class, function () {
            return new Assets();
        });

        $this->app->singleton(Blocks::class, function () {
            return new Blocks();
        });

        $this->app->singleton(DynamicChoiceSources::class, function () {
            return new DynamicChoiceSources();
        });

        $this->app->singleton(Integrations::class, function () {
            return new Integrations();
        });

        $this->app->singleton(Forms::class, function () {
            return new Forms();
        });

        $this->app->singleton(FormRows::class, function () {
            return new FormRows();
        });

        $this->app->singleton(Fields::class, function () {
            return new Fields();
        });

        $this->app->singleton(FieldGroups::class, function () {
            return new FieldGroups();
        });

        $this->app->singleton(FormActions::class, function () {
            return new FormActions();
        });

        $this->app->singleton(FieldWrappers::class, function () {
            return new FieldWrappers();
        });

        $this->app->singleton(Tables::class, function () {
            return new Tables();
        });

        $this->app->singleton(MenuPages::class, function () {
            return new MenuPages();
        });

        $this->app->singleton(MenuPageTemplates::class, function () {
            return new MenuPageTemplates();
        });

        $this->app->singleton(PostTypes::class, function () {
            return new PostTypes();
        });

        $this->app->singleton(PostMetaDefinitions::class, function () {
            return new PostMetaDefinitions();
        });

        $this->app->singleton(UserMetaDefinitions::class, function () {
            return new UserMetaDefinitions();
        });

        $this->app->singleton(PackagesRegister::class, function () {
            return new PackagesRegister();
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

        $this->app->alias(AssetGroups::class, 'meros.registers.asset_groups');
        $this->app->alias(Assets::class, 'meros.registers.assets');
        $this->app->alias(Blocks::class, 'meros.registers.blocks');
        $this->app->alias(DynamicChoiceSources::class, 'meros.registers.dynamic_choice_sources');
        $this->app->alias(Integrations::class, 'meros.registers.integrations');
        $this->app->alias(Forms::class, 'meros.registers.forms');
        $this->app->alias(FormRows::class, 'meros.registers.form_rows');
        $this->app->alias(Fields::class, 'meros.registers.fields');
        $this->app->alias(FieldGroups::class, 'meros.registers.field_groups');
        $this->app->alias(FormActions::class, 'meros.registers.form_actions');
        $this->app->alias(FieldWrappers::class, 'meros.registers.field_wrappers');
        $this->app->alias(Tables::class, 'meros.registers.tables');
        $this->app->alias(MenuPages::class, 'meros.registers.menu_pages');
        $this->app->alias(MenuPageTemplates::class, 'meros.registers.menu_page_templates');
        $this->app->alias(PackagesRegister::class, 'meros.registers.packages');
        $this->app->alias(PostTypes::class, 'meros.registers.post_types');
        $this->app->alias(PostMetaDefinitions::class, 'meros.registers.post_meta_definitions');
        $this->app->alias(UserMetaDefinitions::class, 'meros.registers.user_meta_definitions');
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

        // Register integrations controller
        $this->app->singleton(IntegrationsController::class, function () {
            return new IntegrationsController();
        });

        // Register installer controller
        $this->app->singleton(InstallerController::class, function () {
            return new InstallerController();
        });

        // Register REST controller
        $this->app->singleton(RestController::class, function () {
            return new RestController();
        });

        // Register the vite assets class
        $this->app->singleton(ViteAssets::class, function () {
            return new ViteAssets();
        });

        // Alias the context class (used in Context Facade)
        $this->app->alias(Context::class, 'meros.context');

        // Add blade directive for vite assets
        Blade::directive('viteAssets', function (?string $context = null, ?string $entry = null) {
            $context  = $context ?: 'theme';
            $context  = trim($context, '\'"'); // Remove quotes if present
            $vitePath = null;
            $instance = null;

            $isPackageContext = !in_array($context, ['theme', 'framework']);

            if ($isPackageContext) {
                $instance = Packages::all()->where('handle', $context)->first();
            }

            else if ($context === 'theme') {
                $instance  = Theme::get();
            } 
            
            else if ($context === 'framework') {
                $instance  = FrameworkAccessor::get();
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