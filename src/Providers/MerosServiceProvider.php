<?php

namespace MM\Meros\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

use MM\Meros\Contracts\Meros;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Plugin;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\PluginInfo;
use MM\Meros\Helpers\Features;

use MM\Meros\CoreFeatures\DynamicPage;

class MerosServiceProvider extends ServiceProvider
{
    protected array $coreFeatures = [
        'meros_dynamic_page' => [
            'category'    => 'miscellaneous',
            'optionGroup' => 'meros_theme_settings',
            'class'       => DynamicPage::class
        ]
    ];

    public function register(): void
    {
        $this->app->singleton(
            Meros::class, fn($app) => new Meros($app)
        );

        define('MEROS', true);
    }

    public function boot(): void
    {
        $meros = $this->app->make(Meros::class);

        $this->loadCoreFeatures( $meros );
        $this->loadPlugins();

        do_action('meros_add_features', $meros);

        $meros->bootstrap();
    }

    protected function loadCoreFeatures( object $meros ): void
    {
        $features = apply_filters('meros_core_features', $this->coreFeatures);

        foreach ( $features as $name => $feature ) {
            
            $args = [
                'name'     => $name,
                'fullName' => 'mirror_and_mountain_' . $name,
                'dotName'  => 'mirror_and_mountain.' . $name
            ];

            $featureArgs = array_merge($feature, $args);

            $class = ClassInfo::get( $featureArgs['class'] );
            if ( ! $class->isDescendantOf( Feature::class ) ) { continue; }

            $instance = Features::instantiate( $this->app, $class, $featureArgs );
            $meros->__addInstantiatedFeature( $featureArgs['fullName'], $instance );
        }
    }

    protected function loadPlugins(): void
    {
        $featuresDir = app_path('Features');
        $pluginsDir  = base_path('plugins');
        $pluginPaths = File::glob( $pluginsDir . '/*', GLOB_ONLYDIR );

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
            $classPath = "{$featuresDir}/{$className}.php";
            $class     = ClassInfo::getFromPath( $classPath );

            if ( $class->extends( Plugin::class ) ) {

                add_action('meros_add_features', function ( $theme ) use ( $pluginInfo, $class ) {
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
}
