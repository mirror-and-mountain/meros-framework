<?php 

namespace MM\Meros\Helpers;

use MM\Meros\Helpers\Features;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\PluginInfo;

use MM\Meros\Contracts\Extension;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Plugin;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Foundation\Application;

class ExtensionLoader
{
    public array $extensions;
    public array $features;
    public array $plugins;
    private object $theme;

    private function __construct( private Application $app, object $theme )
    {
        $this->theme = $theme;
    }

    public static function init( Application $app, object $theme ): self
    {
        $instance    = new self( $app, $theme );
        $themeConfig = base_path('config/theme.php');

        if ( File::exists( $themeConfig ) ) {
            $instance->extensions = Config::get('theme.extensions') ?? [];
            $instance->features   = Config::get('theme.features') ?? [];
            $instance->plugins    = Config::get('theme.plugins') ?? [];
        }

        return $instance;
    }

    public function loadExtensions( string $type ): void
    {
        $extensionPath = app_path( ucfirst( $type ) );

        if ( !File::exists( $extensionPath ) ) {
            return;
        }

        $extensionDefs = [];
        $baseClass     = '';

        switch ( $type ) {
            case 'extensions':
                $extenstionDefs = $this->extensions ?? [];
                $baseClass      = Extension::class;
                break;

            case 'plugins': 
                $extenstionDefs = $this->plugins ?? [];
                $baseClass      = Plugin::class;
                break;
            
            case 'features':
                $extenstionDefs = $this->features ?? [];
                $baseClass      = Feature::class;
                break;
        }

        if ( $extensionDefs === [] || $baseClass === '' ) {
            return;
        }

        foreach ( $extenstionDefs as $class => $files ) {
            $this->loadExtension( $extensionPath, $class, $files, $baseClass );
        }
    }

    private function loadExtension( string $extPath, string $class, string|array $files, string $baseClass ): void
    {
        $path = is_string( $files ) 
                ? trailingslashit( $extPath ) . $files
                : trailingslashit( $extPath ) . $files['config'] ?? '';

        if ( !File::exists( $path ) || !File::isFile( $path ) ) {
            return;
        }

        require_once( $path );

        $classInfo = ClassInfo::get( $class );
        $feature   = false;

        if ( 
            !class_exists( $classInfo->name ) ||
            !$classInfo->isDescendantOf( $baseClass )
        ) {
            return;
        }

        switch ( $baseClass ) {
            case Extension::class:
                $parent      = $classInfo->parent;
                $parentInfo  = ClassInfo::get( $parent );
                $featurePath = $parentInfo->path;
                $featureUri  = $parentInfo->uri;

                $feature = Features::instantiate( 
                    $this->app, $classInfo->name, $featurePath, $featureUri 
                );

                break;
            
            case Plugin::class:
                $featurePath = $classInfo->path;
                $featureUri  = $classInfo->uri;
                $pluginDir   = dirname( $files['src'] );

                if ( !File::isDirectory( $pluginDir ) ) {
                    return;
                }

                $pluginInfo = PluginInfo::get( $pluginDir ) ?? false;

                if ( !$pluginInfo ) {
                    return;
                }

                $feature = Features::instantiate(
                    $this->app, $classInfo->name, $featurePath, $featureUri, $pluginInfo
                );

                break;
            
            case Feature::class: 
                $featurePath = $classInfo->path;
                $featureUri  = $classInfo->uri;

                $feature = Features::instantiate( 
                    $this->app, $classInfo->name, $featurePath, $featureUri 
                );

                break;
        }

        if ( $feature === false ) {
            return;
        }

        $author = $feature->getAuthor();
        $author = Str::slug( $author['name'] ?? 'unknown', '_' );
        $name   = $feature->getName();

        $featureName = $author . '.' . $name;

        $this->theme->addFeature( $featureName, $feature );
    }
}