<?php

namespace MM\Meros\App\Support\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\FeatureProvider;

use MM\Meros\App\Support\Asset;
use MM\Meros\App\Support\Block;

class Discover {
    protected string $assetsPath;
    protected string $blocksPath;

    public function __construct(protected FeatureProvider $source) {
        $this->assetsPath = $this->source->getPreference('assets_path');
        $this->blocksPath = $this->source->getPreference('blocks_path');
    }

    /**
     * Resolves a given path, checking both the provided path and a potential path relative to the source's base path.
     *
     * @param string|null  $path The path to resolve. If null, the source's default assets path will be used.
     * @param string       $defaultPath The default path to use if $path is null. This should be the source's default assets path.
     * 
     * @return string|null The resolved path if it exists and is a directory, or null if not found.
     */
    private function resolvePath(?string $path, string $defaultPath): ?string {
        if ($path === null) {
            $path = $defaultPath;
        }

        if (File::exists($path) && File::isDirectory($path)) {
            return $path;
        }

        $providerBasePath  = $this->source->getPath();
        $potentialPath     = rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        return File::exists($potentialPath) && File::isDirectory($potentialPath) ? $potentialPath : null;
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
     * Discovers and registers assets for the source item based on the configured assets path.
     *
     * @return void
     */
    public function assets(?string $path = null): void {
        $path = $this->resolvePath($path, $this->assetsPath);

        // Check the assets path exists and is a directory
        if ($path === null || !File::exists($path) || !File::isDirectory($path)) {
            return;
        }

        // Valid locations for assets
        $locations = ['admin', 'editor', 'site'];

        // Get subdirectories
        $directories = File::directories($path);

        foreach ($directories as $directory) {
            $group = basename($directory);

            if (in_array($group, $locations)) {
                $location = $group;

                $files = collect(File::files($directory))
                    ->filter(function ($file) {
                        return in_array($file->getExtension(), ['js', 'css']);
                    })
                    ->all();

                $this->processAssetLocation($location, $group, $files);
            }

            else {
                $subdirectories = File::directories($directory);
                foreach ($subdirectories as $subdirectory) {
                    $location = basename($subdirectory);

                    if (!in_array($location, $locations)) {
                        continue; // Skip directories not in valid locations
                    }

                    $files = collect(File::files($subdirectory))
                        ->filter(function ($file) {
                            return in_array($file->getExtension(), ['js', 'css']);
                        })
                        ->all();

                    $this->processAssetLocation($location, $group, $files);
                }
            }
        }
    }

    /**
     * Discovers and registers blocks for the source item based on the configured blocks path.
     *
     * @param string|null $path
     *
     * @return void
     */
    public function blocks(?string $path = null): void {
        $path = $this->resolvePath($path, $this->blocksPath);

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
            $block = app(Block::class, ['source' => $this->source])
                ->make($config['name'], $blockPath);

            // Set additional block properties from config
            if (isset($config['title'])) {
                $block->title($config['title']);
            }

            if (isset($config['description'])) {
                $block->description($config['description']);
            }

            $this->source->registry()->add('blocks', $block);
        }
    }

    /**
     * Processes asset files for a specific location and group, creating and registering Asset objects.
     *
     * @param  string  $location The location for the assets (e.g. 'admin', 'editor', 'site').
     * @param  string  $group The group for the assets, typically the name of the subdirectory they are in.
     * @param  array   $files An array of SplFileInfo objects representing the asset files to process.
     * 
     * @return void
     */
    protected function processAssetLocation(string $location, string $group, array $files): void {
        foreach ($files as $file) {
            $path      = $file->getPathname();
            $extension = $file->getExtension();

            // Set the type
            $type = $extension === 'js' ? 'script' : 'style';

            // Set the group
            $groupIsLocation = $group === $location;

            // Check for config file
            $label = '';
            $desc  = '';

            $configPath = dirname($path) . DIRECTORY_SEPARATOR . 'config.php'; // In the same directory as the asset

            if (!File::exists($configPath)) {
                $configPath = dirname(dirname($path)) . DIRECTORY_SEPARATOR . 'config.php'; // In the parent directory (for grouped assets)
            }

            if (File::exists($configPath)) {
                $config = include $configPath;

                if (is_array($config)) {
                    $label  = $config['label'] ?? '';
                    $desc   = $config['description'] ?? '';
                }
            }

            // Check for dependency file
            $dependencies = [];
            $wpFile = false;

            $dependencyPath = dirname($path) . DIRECTORY_SEPARATOR . 'dependencies.php'; // Custom dependencies file in the same directory

            if (!File::exists($dependencyPath)) {
                $dependencyPath = dirname($path) . DIRECTORY_SEPARATOR . 'index.asset.php'; // WordPress-style asset file in the same directory
                $wpFile = File::exists($dependencyPath);
            }

            if (File::exists($dependencyPath)) {
                $dependencyConfig = include $dependencyPath;

                if (is_array($dependencyConfig)) {
                    $dependencies = $wpFile ? $dependencyConfig['dependencies'] ?? [] : $dependencyConfig;
                }
            }

            // Generate the handle
            $base   = Str::slug($this->source->getName()) . '-';
            $handle = $base . ($groupIsLocation ? '' : $group . '-') . $location . '-' . $type;

            // Generate label if not set in config
            if ($label === '') {
                $label = Str::title(Str::replace(['-', '_'], ' ', $location)) . ' ' . ($type === 'script' ? 'Script' : 'Style');
            }

            // Create and register the asset
            $asset = app(Asset::class, [
                'source' => $this->source
            ]);
            
            $asset
                ->$type($path)
                ->handle($handle)
                ->location($location)
                ->group($group)
                ->label($label)
                ->description($desc)
                ->dependencies($dependencies);

            $this->source->registry()->add('assets', $asset);
        }
    }
}