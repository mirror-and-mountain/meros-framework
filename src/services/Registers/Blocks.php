<?php 

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Facades\File;

use MM\Meros\Services\Contracts\Block;
use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Registers\Interfaces\Discovery;

class Blocks extends Register implements Discovery {
    protected string $identifier = 'name';
    protected string $definition = Block::class;

    use Concerns\Discovers;

    /**
     * Discovers blocks in the specified path and registers them.
     *
     * @param string|null $path The path to discover blocks from. If null, the provider's default blocks path will be used.
     *
     * @return self
     */
    public function discover(?string $path = null): self {
        $this->ensureCheckedOut();
        $this->discoverBlocks($path);
        $this->checkin(); // Check the register back in after discovery
        return $this;
    }

    /**
     * Parses properties for the block's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];

        return [
            'name' => $props['name'] ?? '',
            'path' => $props['path'] ?? '',
            'args' => [
                'api_version'           => $args['api_version'] ?? 1,
                'title'                 => $args['title'] ?? '',
                'description'           => $args['description'] ?? '',
                'textdomain'            => $args['textdomain'] ?? '',
                'render_callback'       => $args['render_callback'] ?? null,
                'category'              => $args['category'] ?? '',
                'icon'                  => $args['icon'] ?? '',
                'keywords'              => $args['keywords'] ?? [],
                'parent'                => $args['parent'] ?? [],
                'ancestor'              => $args['ancestor'] ?? [],
                'allowed_blocks'        => $args['allowed_blocks'] ?? [],
                'provides_context'      => $args['provides_context'] ?? [],
                'uses_context'          => $args['uses_context'] ?? [],
                'supports'              => $args['supports'] ?? [],
                'attributes'            => $args['attributes'] ?? [],
                'styles'                => $args['style_variations'] ?? [],
                'variations'            => $args['variations'] ?? [],
                'selectors'             => $args['selectors'] ?? [],
                'variation_callback'    => $args['variation_callback'] ?? null,
                'script_handles'        => $args['script_handles'] ?? [],
                'style_handles'         => $args['style_handles'] ?? [],
                'editor_script_handles' => $args['editor_script_handles'] ?? [],
                'editor_style_handles'  => $args['editor_style_handles'] ?? [],
                'view_script_handles'   => $args['view_script_handles'] ?? [],
                'view_style_handles'    => $args['view_style_handles'] ?? [],
            ]
        ];
    }

    /**
     * Discovers and registers blocks for the provider item based on the configured blocks path.
     *
     * @param string|null $path
     *
     * @return void
     */
    protected function discoverBlocks(?string $path = null): void {
        $checkedOutTo = $this->provider;

        $path = $this->resolvePath($path, $this->provider->getPreference('blocks_path'));

        // Check the blocks path exists and is a directory
        if ($path === null || !File::exists($path) || !File::isDirectory($path)) {
            return;
        }

        $candidates = File::glob($path . '/*', GLOB_ONLYDIR);

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
            $this->make([
                'name' => $config['name'],
                'path' => $blockPath,
                'args' => $config,
            ]);

            $this->checkout($checkedOutTo); // Checkout the register for the next iteration
        }
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
}