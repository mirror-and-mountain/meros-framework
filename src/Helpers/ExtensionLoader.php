<?php 

namespace MM\Meros\Helpers;

use MM\Meros\Contracts\Extension;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Plugin;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ExtensionLoader
{
    public array $extensions;
    public array $features;
    public array $plugins;

    private function __construct( private object $theme )
    {
        // Do nothing
    }

    public static function init( object $theme ): self
    {
        $instance    = new self( $theme );
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
        $extPath = app_path( ucfirst( $type ) );

        if ( !File::exists( $extPath ) ) {
            return;
        }

        $extensionDefs = [];
        $baseClass     = '';

        switch ( $type ) {
            case 'extensions':
                $extensionDefs = $this->extensions;
                $baseClass     = Extension::class;
                break;

            case 'plugins': 
                $extensionDefs = $this->plugins ?? [];
                $baseClass     = Plugin::class;
                break;
            
            case 'features':
                $extensionDefs = $this->features ?? [];
                $baseClass     = Feature::class;
                break;
        }

        if ( $extensionDefs === [] || $baseClass === '' ) {
            return;
        }

        foreach ( $extensionDefs as $class => $files ) {
            $this->loadExtension( $extPath, $class, $files, $baseClass );
        }
    }

    private function loadExtension( 
        string $extPath, 
        string $class, 
        string|array $files, 
        string $baseClass 
    ): void
    {
        if ( is_string( $files ) ) {
            switch ( $baseClass ) {
                case Feature::class:
                    $path = trailingslashit( $extPath ) . trailingslashit( File::name( $files ) ) . $files;
                    break;
                
                default:
                    $path = trailingslashit( $extPath ) . $files;
            }
        } 
        
        else if ( is_array( $files ) && array_key_exists( 'config', $files ) ) {
            $path = trailingslashit( $extPath ) . $files['config'];
        } 

        if ( !File::exists( $path ) || !File::isFile( $path ) ) { return; }

        require_once $path;

        $class   = ClassInfo::get( $class );
        $feature = false;

        if ( 
            !$class ||
            !$class->isDescendantOf( $baseClass )
        ) {
            return;
        }

        switch ( $baseClass ) {
            case Extension::class:
                $parent      = ClassInfo::get( $class->parent );
                $featurePath = $parent->path;
                $featureUri  = $parent->uri;

                $feature = Features::instantiate( 
                    $class->name, $featurePath, $featureUri 
                );

                break;
            
            case Plugin::class:
                $featurePath = $class->path;
                $featureUri  = $class->uri;

                if ( !isset( $files['src'] ) ) { return; }
                
                $pluginDir = base_path( dirname( $files['src'] ) );

                if ( !File::isDirectory( $pluginDir ) ) {
                    return;
                }

                $pluginInfo = PluginInfo::get( $pluginDir ) ?? false;

                if ( !$pluginInfo ) { return; }

                $feature = Features::instantiate(
                    $class->name, $featurePath, $featureUri, $pluginInfo
                );

                break;
            
            case Feature::class: 
                $featurePath = $class->path;
                $featureUri  = $class->uri;

                $feature = Features::instantiate( 
                    $class->name, $featurePath, $featureUri 
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