<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait AssetManager
{
    
    protected bool  $hasAssets  = false;
    protected array $assetTypes = [
        'admin'  => 'admin_enqueue_scripts', 
        'editor' => 'enqueue_block_editor_assets', 
        'site'   => 'wp_enqueue_scripts'
    ];
    
    protected string $assetsDir  = 'assets/build';
    protected array  $scripts    = [];
    protected array  $scriptDeps = [];
    protected bool   $putScriptsInFooter = false;

    protected array  $styles    = [];
    protected array  $styleDeps = [];

    protected array $registeredScripts = [];
    protected array $registeredStyles  = [];

    protected bool  $useFullNameForAssets = true;

    final protected function loadAssets(): void
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

        $this->hasAssets = $this->registeredScripts !== [] || $this->registeredStyles !== [];
    }

    final protected function setAssets( string $path, string $type, string $extension ): void
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
                $this->scripts[ $type ][ $handle ] = Str::replace( $this->path, $this->uri, $asset );

            } elseif ( $extension === 'css' ) {

                $this->styleDeps[ $type ][ $handle ] = file_exists( $dependancyFile ) ? include $dependancyFile : [];
                $this->styles[ $type ][ $handle ] = Str::replace( $this->path, $this->uri, $asset );
                
            }

            $i++;
        }
    }

    final protected function registerAssets(): void
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

    final protected function enqueueAssets(): void
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
