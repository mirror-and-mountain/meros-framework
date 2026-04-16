<?php

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Discoverables\Block;

trait HasBlocks {
    /**
     * Whether this item should automatically discover blocks.
     *
     * @var bool
     */
    protected bool $discoverBlocks = false;

    /**
     * Instantiates a new Block class and returns it for configuration.
     *
     * @return Block
     */
    protected function blocks(): Block {
        return app(Block::class, ['source' => $this]);
    }

    /**
     * Discovers blocks to be registered using the item's blocks path.
     *
     * @return void
     */
    protected function discoverBlocks(): void {
        if (! $this->discoverBlocks) {
            return;
        }

        $blocksPath = $this->getPreference('blocks_path');

        if (!File::exists($blocksPath) || !File::isDirectory($blocksPath)) {
            return;
        }

        $candidates = File::glob($blocksPath . '/*', GLOB_ONLYDIR);

        foreach ($candidates as $blockPath) {
            $blockJsonPath = trailingslashit($blockPath) . 'block.json';
            $config        = $this->getBlockUserConfig($blockJsonPath);

            if ($config === null || !is_array($config)) {
                continue;
            }

            if (!isset($config['name'])) {
                continue;
            }

            // Make the block
            $this->makeBlock($this->makeBlockConfig($config, $blockPath));
        }
    }

    /**
     * Generates the config array for a block.
     *
     * @param  array  $config
     * @param  string $path
     *
     * @return array
     */
    private function makeBlockConfig(array $config, string $path): array {
        $name        = $config['name'];
        $label       = $config['title'] ?? '';
        $description = $config['description'] ?? '';
        $handle      = Str::replace(['/', '-'], '_', $name);

        // Determine if the block should be enabled based on preferences.
        $enabled = $this->getPreference('blocks_are_enabled_by_default');
        
        // Determine if the block should be switchable based on preferences.
        $isSwitchableByDefault = $this->getPreference('blocks_are_switchable_by_default');
        $isSwitchable          = apply_filters($handle . '_block_is_switchable', $isSwitchableByDefault);

        if ($isSwitchable && ($label === '' || $description === '')) {
            $isSwitchable = false;
        }

        return [
            'name'          => $name,
            'path'          => $path,
            'handle'        => $handle,
            'label'         => $label,
            'description'   => $description,
            'enabled'       => $enabled,
            'is_switchable' => $isSwitchable
        ];
    }

    /**
     * Retrieves and decodes the block configuration from the given block.json path.
     *
     * @param  string $blockJsonPath The path to the block.json file.
     * 
     * @return array|null The decoded block configuration array, or null if the file is invalid.
     */
    private function getBlockUserConfig(string $blockJsonPath): array|null {
        if (!File::exists($blockJsonPath) || !File::isFile($blockJsonPath)) {
            return null;
        }

        return File::json($blockJsonPath);
    }

    /**
     * Creates a block instance from the given config and registers it.
     *
     * @param  array|string $configOrHandle The block configuration array or a handle string.
     * 
     * @return Block The created block instance.
     */
    protected function makeBlock(array|string $configOrHandle): Block {
        return app(
            Block::class, [
                'source' => $this,
            ]
        )->make($configOrHandle);
    }

    /**
     * Returns a collection of block objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only blocks that are ready.
     *
     * @return Collection
     */
    final public function getBlocks(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->registry->get('blocks')
                    ->where('source', $this)
                    ->where('ready', true) ?? collect([]);
        } else {
            return $this->registry->get('blocks')
                    ->where('source', $this) ?? collect([]);
        }
    }

    /**
     * Returns a specific block object registered by the item.
     *
     * @param  string $handle The handle of the block to return.
     * 
     * @return Block|null
     */
    final public function getBlock(string $handle): Block|null {
        $block = collect($this->getBlocks())->firstWhere('handle', $handle) ?? null;

        return $block ?: null;
    }
}