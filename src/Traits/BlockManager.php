<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait BlockManager
{
    protected bool   $hasBlocks = false;
    protected string $blocksDir = 'blocks/build';
    protected array  $blocks    = [];

    private function loadBlocks(): void
    {
        $blocksPath = $this->path . $this->blocksDir;
        
        $this->setBlocks( $blocksPath );

        if ($this->blocks !== []) {
            $this->defaultSettings['blocks'] = $this->blocks;
        }

        $this->hasBlocks = $this->blocks !== [];
    }

    private function setBlocks( string $path ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }
        
        $candidates = File::glob( $path . '/*', GLOB_ONLYDIR );
        
        foreach ( $candidates as $blockPath ) {

            $name = Str::kebab( basename( $blockPath ) );

            if ( File::exists( trailingslashit( $blockPath ) . 'block.json' ) ) {

                $this->blocks[ $name ] = [
                    'enabled' => true,
                    'path'    => $blockPath
                ];

            } 
        }
    }

    private function registerBlocks(): void
    {   
        add_action('init', function () {

            foreach ( $this->currentSettings['blocks'] ?? [] as $block ) {
                if ( ! is_array($block) ) { continue; }
                if ( ! $block['enabled'] ) { continue; }
                register_block_type( $block['path'] );
            }

        });
    }
}