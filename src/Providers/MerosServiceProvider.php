<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Plugin;
use MM\Meros\Contracts\ThemeManager;

use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\PluginInfo;
use MM\Meros\Helpers\Features;

use MM\Meros\DynamicPage\Feature as DynamicPage;

class MerosServiceProvider extends ServiceProvider
{
    private bool $registered = false;

    public function register(): void
    {
        $themeClass     = apply_filters('meros_site_class', 'App\\Theme');
        $themeClassInfo = ClassInfo::get($themeClass);

        if ( $themeClassInfo->extends(ThemeManager::class) ) {
            $this->app->singleton(
                'meros.theme_manager', fn($app) => new $themeClass($app)
            );
            $this->registered = true;
        }

        define('MEROS', true);
    }

    public function boot(): void
    {
        $this->ensureAppKey();

        if ( $this->registered ) {
            $theme = $this->app->make('meros.theme_manager');
            $this->loadCoreFeatures( $theme );
            $this->loadPlugins();

            do_action('meros_theme_add_features', $theme);

            $theme->initialise();
        }
    }

    private function loadCoreFeatures( object $site ): void
    {
        $this->enableSPA( $site );
    }

    private function loadPlugins(): void
    {
        $pluginConfigDir = app_path('Plugins');
        $pluginsDir      = base_path('plugins');
        $pluginPaths     = File::glob( $pluginsDir . '/*', GLOB_ONLYDIR );

        foreach ( $pluginPaths as $pluginPath ) {
            $pluginInfo = PluginInfo::get( $pluginPath );

            if (
                !$pluginInfo ||
                !isset($pluginInfo['Plugin Name']) ||
                !isset($pluginInfo['Author']) ||
                !isset($pluginInfo['File'])
            ) {
                continue;
            }

            $className = Str::studly( basename($pluginPath) );
            $classPath = "{$pluginConfigDir}/{$className}.php";
            $class     = ClassInfo::getFromPath( $classPath );

            if ( $class->extends( Plugin::class ) ) {

                add_action('meros_theme_add_features', function ( $theme ) use ( $pluginInfo, $class ) {
                    $name     = $pluginInfo['Plugin Name'];
                    $category = $pluginInfo['MEROS Category'] ?? 'miscellaneous';
                    $author   = $pluginInfo['Author'];
                    $path     = base_path( $pluginInfo['File'] );

                    $args     = [
                        'path'       => $path,
                        'uri'        => Str::replace( get_theme_file_path(), get_theme_file_uri(), $path ),
                        'pluginInfo' => $pluginInfo
                    ];
        
                    $theme->addFeature( $name, $category, $class->name, $author, $args );

                });
            }
        }
    }

    protected function ensureAppKey(): void
    {
        $envPath = base_path('.env');
        $key = 'base64:' . base64_encode(random_bytes(32));
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

    protected function enableSPA( object $theme ): void
    {
        $spa = $theme->use_single_page_loading;

        if ( !$spa ) {
            return;
        }

        $spaName = 'meros_dynamic_page';
        $spaArgs = [
            'name'        => $spaName,
            'fullName'    => 'mirror_and_mountain_' . $spaName,
            'dotName'     => 'mirror_and_mountain.' . $spaName,
            'category'    => 'miscellaneous',
            'optionGroup' => 'meros_theme_settings',
            'class'       => DynamicPage::class
        ];

        $classInfo = ClassInfo::get( $spaArgs['class'] );
        if ( ! $classInfo->isDescendantOf( Feature::class ) ) { return; }
        $spaArgs['path'] = $classInfo->path;
        $spaArgs['uri']  = $classInfo->uri;
        $spaInstance     = Features::instantiate( $this->app, $spaArgs['class'], $spaArgs );
        $author          = 'MIRROR AND MOUNTAIN';
        
        $theme->__addInstantiatedFeature( $spaArgs['name'], $spaInstance, $author );
    }
}
