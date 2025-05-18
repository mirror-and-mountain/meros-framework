<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait BlockManager
{
    /**
     * Indicates whether the feature has blocks.
     *
     * @var bool
     */
    protected bool $hasBlocks = false;

    /**
     * The directory to search for blocks in relative to the
     * feature directory. Blocks are discovered by the the 
     * existance of a block.json file.
     *
     * @var string
     */
    protected string $blocksDir = 'blocks/build';

    /**
     * Discovered blocks.
     *
     * @var array
     */
    protected array $blocks = [];

    /**
     * Sets absolute path and calls setBlocks.
     *
     * @return void
     */
    private function loadBlocks(): void
    {
        $blocksPath = $this->path . $this->blocksDir;
        
        $this->setBlocks( $blocksPath );

        if ($this->blocks !== []) {
            $this->defaultSettings['blocks'] = $this->blocks;
        }

        // Resets the hasBlocks indicator depending on whether any blocks have been discovered.
        $this->hasBlocks = $this->blocks !== [];
    }

    /**
     * Uses glob to search for blocks using the given path.
     * A block will be discovered if the given directory
     * includes a valid block.json file.
     *
     * @param  string $path
     * @return void
     */
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

    /**
     * Registers blocks using register_block_type.
     *
     * @return void
     */
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