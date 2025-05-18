<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait AssetManager
{
    /**
     * Indicates whether the feature has assets.
     *
     * @var bool
     */
    protected bool $hasAssets = false;

    /**
     * Maps assets types/directories to Wordpress hooks.
     * Example: assets/build/admin/index.js will be enqueued using
     * admin_enqueue_scripts.
     *
     * @var array
     */
    protected array $assetTypes = [
        'admin'  => 'admin_enqueue_scripts', 
        'editor' => 'enqueue_block_editor_assets', 
        'site'   => 'wp_enqueue_scripts'
    ];
    
    /**
     * The directory to search for assets in relative to the
     * feature directory.
     *
     * @var string
     */
    protected string $assetsDir  = 'assets/build';

    /**
     * Discovered scripts.
     *
     * @var array
     */
    protected array $scripts    = [];

    /**
     * Discovered script dependancies.
     *
     * @var array
     */
    protected array $scriptDeps = [];

    /**
     * Whether to place scripts in the footer.
     *
     * @var bool
     */
    protected bool $putScriptsInFooter = false;

    /**
     * Discovered styles.
     *
     * @var array
     */
    protected array $styles    = [];

    /**
     * Discovered style dependancies.
     *
     * @var array
     */
    protected array $styleDeps = [];

    /**
     * Scripts that have been registered using 
     * wp_register_script.
     *
     * @var array
     */
    protected array $registeredScripts = [];

    /**
     * Styles that have been registered using
     * wp_register_style.
     *
     * @var array
     */
    protected array $registeredStyles  = [];

    /**
     * Determines whether script handles should use
     * the feature's fullName. This can be useful if 
     * the feature has a common name and we need to
     * avoid conflicts.
     *
     * @var bool
     */
    protected bool $useFullNameForAssets = true;

    /**
     * Sets the absolute path and calls setAssets.
     * Continues to register discovered assets.
     *
     * @return void
     */
    private function loadAssets(): void
    {   
        $assetsPath = $this->path . $this->assetsDir;

        foreach ( $this->assetTypes as $type => $_ ) {
    
            if ( $this->scripts === [] ) {
                $this->setAssets( $assetsPath, $type, 'js' );
            }
            
            if ( $this->styles === [] ) {
                $this->setAssets( $assetsPath, $type, 'css' );
            }

        }

        $this->registerAssets();

        // Reset the hasAssets indicator depending on whether any assets have been discovered.
        $this->hasAssets = $this->registeredScripts !== [] || $this->registeredStyles !== [];
    }

    /**
     * Uses glob to search for assets using the given path, type and extension.
     * Sets asset handles to be used in wp_enqueue functions and updates the
     * scripts and styles properties. This method will also discover any
     * dependancies for each asset.
     *
     * @param  string $path
     * @param  string $type
     * @param  string $extension
     * @return void
     */
    private function setAssets( string $path, string $type, string $extension ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $assets = File::glob( "{$path}/{$type}/*.{$extension}" );

        if ( $assets === [] ) {
            return;
        }

        $i = 0;
        foreach ( $assets as $asset ) {

            $pathInfo = pathinfo( $asset );
            $dependancyFile = trailingslashit( $pathInfo['dirname'] ) . $pathInfo['filename'] . 'asset.php';
            $name = $this->useFullNameForAssets ? $this->fullName : $this->name;
            $handle = $name . '_' . $type . '_' . $pathInfo['filename'] . '_' . $i;

            if ( $extension === 'js' ) {

                $this->scriptDeps[ $type ][ $handle ] = file_exists( $dependancyFile ) ? include $dependancyFile : [];
                $this->scripts[ $type ][ $handle ]    = Str::replace( $this->path, $this->uri, $asset );

            } elseif ( $extension === 'css' ) {

                $this->styleDeps[ $type ][ $handle ] = file_exists( $dependancyFile ) ? include $dependancyFile : [];
                $this->styles[ $type ][ $handle ]    = Str::replace( $this->path, $this->uri, $asset );
                
            }

            $i++;
        }
    }

    /**
     * Registers discovered assets using wp_register_* functions.
     *
     * @return void
     */
    private function registerAssets(): void
    {
        add_action('init', function () {
            foreach ( $this->assetTypes as $type => $_ ) {
                $i = 0;
                foreach ( $this->scripts[ $type ] ?? [] as $handle => $src ) {
                    if ( !is_string( $handle ) ) {
                        $handle = "{$this->name}_{$type}_script_{$i}";
                    }

                    $registered = wp_register_script(
                        $handle,
                        $src,
                        $this->scriptDeps[ $type ][ $handle ] ?? [],
                        filemtime(Str::replace($this->uri, $this->path, $src)),
                        $this->putScriptsInFooter
                    );

                    if ( $registered !== false ) {
                        $this->registeredScripts[ $type ][ $handle ] = $src; 
                    }
                    $i++;
                }

                $i = 0;
                foreach ( $this->styles[ $type ] ?? [] as $handle => $src ) {
                    if ( !is_string( $handle ) ) {
                        $handle = "{$this->name}_{$type}_style_{$i}";
                    }

                    $registered = wp_register_style(
                        $handle,
                        $src,
                        $this->styleDeps[ $type ][ $handle ] ?? [],
                        filemtime(Str::replace($this->uri, $this->path, $src))
                    );

                    if ( $registered !== false ) {
                        $this->registeredStyles[ $type ][ $handle ] = $src; 
                    }
                    $i++;
                }
            }
        });
    }

    /**
     * Enqueues assets using wp_enqueue_* functions.
     *
     * @return void
     */
    private function enqueueAssets(): void
    {
        foreach ( $this->assetTypes as $type => $hook ) {
            add_action( $hook, function () use ( $type ) {
                foreach ( $this->registeredScripts[ $type ] ?? [] as $handle => $_ ) {
                    wp_enqueue_script( $handle );
                }
                foreach ( $this->registeredStyles[ $type ] ?? [] as $handle => $_ ) {
                    wp_enqueue_style( $handle );
                }
            });
        }
    }
}
