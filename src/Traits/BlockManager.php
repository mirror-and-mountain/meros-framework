<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait BlockManager {
    /**
     * Indicates whether the feature has blocks.
     * 
     * @var boolean
     */
    private bool $hasBlocks = false;

    /**
     * Whether to allow block enabling/disabling
     * via the settings page by default.
     *
     * @var boolean
     */
    protected bool $allowBlockSwitchingByDefault = true;

    /**
     * The directory to search for blocks in relative to the
     * feature directory. Blocks are discovered by the the
     * existance of a block.json file.
     * 
     * @var string
     */
    protected string $blocksDir = 'blocks/build';

    /**
     * Loaded blocks.
     *
     * @var array
     */
    private array $blocks = [];

    /**
     * Sets absolute path and calls findBlocks.
     * 
     * @return void
     */
    protected function loadBlocks(): void {
        $blocksPath = $this->path . $this->blocksDir;
        $this->findBlocks($blocksPath);
    }

    /**
     * Uses glob to search for blocks using the given path.
     * A block will be discovered if the given directory
     * includes a valid block.json file.
     * 
     * @param string $path
     * @return void
     */
    private function findBlocks(string $path): void {
        if (! File::exists($path)) {
            return;
        }

        $candidates = File::glob($path . '/*', GLOB_ONLYDIR);

        foreach ($candidates as $blockPath) {
            $name = Str::kebab(basename($blockPath));

            if (File::exists(trailingslashit($blockPath) . 'block.json')) {
                $this->addBlock(
                    $name,
                    $blockPath,
                    true,
                    $this->allowBlockSwitchingByDefault
                );
            }
        }
    }

    /**
     * Adds a block to the blocks array.
     *
     * @param string $name
     * @param string $path
     * @param bool $enabledByDefault
     * @param bool $allowSwitching
     * @param bool $isExperimental
     * @return void
     */
    protected function addBlock(
        string $name,
        string $path,
        bool $enabledByDefault = true,
        bool $allowSwitching = true,
        bool $isExperimental = false
    ): void {
        $switchSetting = '';
        $enabled = $enabledByDefault;
        $blockJson = $this->getBlockJson($path);
        $description = is_array($blockJson) && array_key_exists('description', $blockJson)
            ? $blockJson['description']
            : '';

        if ($allowSwitching) {
            $hookName = $this->fullName . '_' . Str::slug($name, '_');
            $isSwitchable = apply_filters($hookName . '_is_switchable', true);

            if ($isSwitchable) {
                $experimental = apply_filters($hookName . '_is_experimental', $isExperimental);
                $settingName = $this->createSwitch(
                    'block',
                    $name,
                    'theme_settings',
                    'blocks',
                    $description,
                    $experimental
                );

                if (is_string($settingName)) {
                    $switchSetting = get_option($settingName, $enabledByDefault);
                    $enabled = $switchSetting === '1' || $switchSetting === 1 || $switchSetting === true;
                }
            }
        }

        $this->blocks[$name] = [
            'enabled'    => $enabled,
            'switchable' => $allowSwitching,
            'path'       => $path,
            'json'       => $blockJson,
        ];

        $this->hasBlocks = true;
    }

    /**
     * Retrieves and decodes the block.json file for a block.
     * 
     * @param string $path
     * @return array|string
     */
    private function getBlockJson(string $path): array|string {
        $blockJsonPath = trailingslashit($path) . 'block.json';

        if (! File::exists($blockJsonPath)) {
            return '';
        }

        return File::json($blockJsonPath);
    }

    /**
     * Registers blocks using register_block_type.
     * 
     * @return void
     */
    private function registerBlocks(): void {
        add_action('init', function () {
            foreach ($this->blocks ?? [] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (! $block['enabled']) {
                    continue;
                }
                register_block_type($block['path']);
            }
        });
    }
}
