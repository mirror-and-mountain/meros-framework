<?php 

namespace MM\Meros\Helpers;

use MM\Meros\Contracts\Extension;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Plugin;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

/**
 * Provides utilities for loading the theme's
 * features, extensions and plugins.
 */
class Loader
{
    // Properties set by the init() method
    public array  $extensions;
    public array  $features;
    public array  $plugins;

    /**
     * The instantiated theme manager. Used to bind
     * valid features to the object.
     *
     * @var object
     */
    public object $theme;

    /**
     * Collects and sets feature parameters from the theme
     * config file located in config/theme.php. Returns an
     * instance of the Loader for further inspection and usage
     * by the caller.
     *
     * @param  object $theme
     * @return self
     */
    public static function init( object $theme ): self
    {
        $instance        = new self();
        $instance->theme = $theme;
        $themeConfig     = base_path('config/theme.php');

        // Check the theme config file exists
        if ( File::exists( $themeConfig ) ) {
            $instance->extensions = Config::get('theme.extensions') ?? [];
            $instance->features   = Config::get('theme.features') ?? [];
            $instance->plugins    = Config::get('theme.plugins') ?? [];
        }

        return $instance;
    }

    /**
     * Loads features of the given type. This includes validating then
     * instantiating each feature's class and adding them to the theme's 
     * feature array.
     *
     * @param  string $type
     * @return void
     */
    public function load( string $type ): void
    {
        // Check the path to the feature's configuation class
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

        // Validate and load each feature of the given type
        foreach ( $extensionDefs as $class => $files ) {
            $this->loadItem( $extPath, $class, $files, $baseClass );
        }
    }

    /**
     * Validates and loads individual features, binding them to 
     * the theme's main class.
     *
     * @param  string       $extPath
     * @param  string       $class
     * @param  string|array $files
     * @param  string       $baseClass
     * @return void
     */
    private function loadItem( 
        string $extPath, 
        string $class, 
        string|array $files, 
        string $baseClass 
    ): void
    {
        if ( is_string( $files ) ) {
            switch ( $baseClass ) {
                // Features live in their own directory under app/Features by default
                case Feature::class:
                    $path = trailingslashit( $extPath ) . trailingslashit( File::name( $files ) ) . $files;
                    break;
                
                // Approach used for extensions
                default:
                    $path = trailingslashit( $extPath ) . $files;
            }
        } 
        
        // This approach is used exclusively for plugins
        else if ( is_array( $files ) && array_key_exists( 'config', $files ) ) {
            $path = trailingslashit( $extPath ) . $files['config'];
        } 

        // Check the main feature class file exists
        if ( !File::exists( $path ) || !File::isFile( $path ) ) { return; }

        require_once $path;

        $class   = ClassInfo::get( $class );
        $feature = false;

        // Check the main feature class exists/is loadable
        if ( 
            !$class ||
            !$class->isDescendantOf( $baseClass )
        ) {
            return;
        }

        switch ( $baseClass ) {
            case Extension::class:
                /**
                 * Extensions should always use their path and uri
                 * (not those of the override class in the theme).
                 */
                $parent      = ClassInfo::get( $class->parent );
                $featurePath = $parent->path;
                $featureUri  = $parent->uri;

                // Instantiate the extension feature
                $feature = Features::instantiate( 
                    $class->name, $featurePath, $featureUri 
                );

                break;
            
            case Plugin::class:
                $featurePath = $class->path;
                $featureUri  = $class->uri;

                // Check that the plugin's main file exists
                if ( !isset( $files['src'] ) ) { return; }
                
                $pluginDir = base_path( dirname( $files['src'] ) );

                if ( !File::isDirectory( $pluginDir ) ) {
                    return;
                }

                // Get and parse plugin info from the plugin's main file
                $pluginInfo = PluginInfo::get( $pluginDir ) ?? false;

                // Check that plugin info exists
                if ( !$pluginInfo ) { return; }

                // Instantiate the plugin feature
                $feature = Features::instantiate(
                    $class->name, $featurePath, $featureUri, $pluginInfo
                );

                break;
            
            case Feature::class: 
                $featurePath = $class->path;
                $featureUri  = $class->uri;

                // Instantiate the feature
                $feature = Features::instantiate( 
                    $class->name, $featurePath, $featureUri 
                );

                break;
        }

        // Check that the feature has been instantiated
        if ( $feature === false ) {
            return;
        }

        $author = $feature->getAuthor();
        $author = Str::slug( $author['name'] ?? 'unknown', '_' );
        $name   = $feature->getName();

        $featureName = $author . '.' . $name;

        // Bind the feature to the theme manager class
        $this->theme->addFeature( $featureName, $feature );
    }
}