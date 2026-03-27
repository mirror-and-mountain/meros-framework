<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Features\Asset;

trait HasAssets {
    /**
     * Whether this item should automatically discover assets.
     *
     * @var bool
     */
    protected bool $discoverAssets = false;

    /**
     * Asset locations. Later tied to WP hooks for enqueuing.
     *
     * @var array
     */
    private array $assetLocations = ['admin', 'editor', 'site'];

    /**
     * Discovers assets to be enqueued using the item's assets path.
     *
     * @return void
     */
    protected function discoverAssets(): void {
        if (! $this->discoverAssets) {
            return;
        }

        $assetsPath = $this->path . $this->getPreference('assets_path');

        if (!File::exists($assetsPath) || !File::isDirectory($assetsPath)) {
            return;
        }

        foreach ($this->assetLocations as $location) {
            $extensions = ['js', 'css'];

            foreach ($extensions as $extension) {
                // Generate the path structure for the current location and extension.
                $pathStructure = $this->getAssetPathStructure($location, $extension);

                // Look for candidate asset files.
                $assets = File::glob($assetsPath . $pathStructure, GLOB_BRACE);

                if ($assets === []) {
                    continue;
                }

                $i = 0; // Used for handle uniqueness

                foreach ($assets as $asset) {
                    $pathInfo   = pathinfo($asset);
                    $configPath = $this->getAssetConfigPath($pathInfo, 'config');
                    $depsPath   = $this->getAssetConfigPath($pathInfo, 'index.asset');

                    // Get user config if available
                    $userConfig = $this->getAssetUserConfig($configPath);
                    
                    // Get dependencies if available
                    $deps = File::exists($depsPath) ? include $depsPath : [];
                    $deps = is_array($deps['dependencies'] ?? []) ? $deps['dependencies'] : [];

                    // Generate a unique handle
                    $handle = $this->generateAssetHandle($pathInfo, $location, $i);

                    // Get the asset src
                    $src = Str::replace($this->path, $this->uri, $asset);

                    // Generate config
                    $config = $this->makeAssetConfig(
                        handle: $handle,
                        type: $extension,
                        location: $location,
                        path: $asset,
                        src: $src,
                        label: $userConfig['label'] ?? '',
                        description: $userConfig['description'] ?? '',
                        conditions: $userConfig['conditions'] ?? [],
                        dependencies: $deps,
                    );

                    // Make the asset
                    $this->makeAsset($config);

                    $i++; // Increment the index used in handle generation
                }
            }
        }
    }

    /**
     * Creates an Asset instance from the given config and registers it.
     *
     * @param  array $config Config for the asset.
     * 
     * @return Asset The created Asset instance.
     */
    protected function makeAsset(array $config): Asset {        
        return app(
            Asset::class, [
                'source' => $this
            ]
        )->make($config);
    }

    /**
     * Generates the config array for an asset.
     *
     * @param  string $handle       The asset handle.
     * @param  string $type         The asset type (e.g., 'js', 'css').
     * @param  string $location     The asset location.
     * @param  string $path         The asset path.
     * @param  string $src          The asset source URL.
     * @param  string $label        The asset label.
     * @param  string $description  The asset description.
     * @param  array  $conditions   Conditions under which the asset should be loaded.
     * @param  array  $dependencies Dependencies for the assets.
     * 
     * @return array The generated config array for the asset.
     */
    private function makeAssetConfig(
        string $handle,
        string $type,
        string $location,
        string $path,
        string $src,
        string $label = '',
        string $description = '',
        array  $conditions = [],
        array  $dependencies = [],
    ): array {
        // Determine if the asset should be enabled based on preferences.
        $enabled = $this->getPreference('assets_are_enabled_by_default');

        // Check for a named directory to determine if the asset should be switchable.
        $hasNamedDir = $this->hasNamedDirectory($location, pathinfo($path)) !== false;
        
        // Determine if the asset should be switchable based on preferences and config.
        if ($hasNamedDir) {
            $isSwitchableByDefault = $this->getPreference('assets_are_switchable_by_default');

            if ($isSwitchableByDefault && $label !== '' && $description !== '') {
                $isSwitchable = apply_filters($handle . '_is_switchable', $isSwitchableByDefault);
            } else {
                $isSwitchable = false;
            }
        } else {
            $isSwitchable = false;
        }

        // Filter enabled for this asset
        $enabled = apply_filters($handle . '_is_enabled', $enabled);

        // Filter position in footer
        $inFooter = apply_filters($handle . '_in_footer', false);
        
        return [
            'handle'        => $handle,
            'type'          => $type,
            'location'      => $location,
            'label'         => $label,
            'description'   => $description,
            'path'          => $path,
            'src'           => $src,
            'conditions'    => $conditions,
            'dependencies'  => $dependencies,
            'enabled'       => $enabled,
            'is_switchable' => $isSwitchable,
            'in_footer'     => $inFooter,
        ];
    }

    /**
     * Retrieves information from an asset's config file if available.
     *
     * @param  string        $configPath
     *
     * @return array|boolean Parsed config array or false if unavailable or invalid.
     */
    private function getAssetUserConfig(string $configPath): array|bool {
        if (File::exists($configPath)) {
            $config = include $configPath;

            return is_array($config) ? [
                'label'       => $config['label'] ?? '',
                'description' => $config['description'] ?? '',
                'conditions'  => $config['conditions'] ?? [],
            ] : false;
        }

        return false;
    }

    /**
     * Generates a unique handle for an asset.
     *
     * @param  array  $pathInfo
     * @param  string $type
     * @param  string $location
     * @param  int    $index
     *
     * @return string The generated handle.
     */
    private function generateAssetHandle(array $pathInfo, string $location, int $index): string {
        $namedDir = $this->hasNamedDirectory($location, $pathInfo);

        if ($namedDir !== false) {
            $name  = $namedDir . '_' . Str::replace('-', '_', $pathInfo['filename']);
        } else {
            $name = Str::replace('-', '_', $pathInfo['filename']);
        }

        $handle = $this->handle . '_' . $location . '_' . Str::replace('-', '_', $name) . '_' . $index;
        return $handle;
    }

    /**
     * Checks whether the asset exists in a named directory i.e. one above the location directory.
     *
     * @param  string  $location
     * @param  array   $pathInfo
     *
     * @return string|false The name of the directory if it exists and is not the same as the location, otherwise false.
     */
    private function hasNamedDirectory(string $location, array $pathInfo): string|false {
        $subDir = Str::afterLast(dirname($pathInfo['dirname']), DIRECTORY_SEPARATOR);
        return $subDir !== $location ? $subDir : false;
    }

     /**
     * Returns the asset path structure by replacing placeholders with actual values.
     *
     * @param  string $location  The asset location (e.g., 'admin', 'editor', 'site').
     * @param  string $extension The asset file extension (e.g., 'js', 'css').
     *
     * @return string The generated asset path structure.
     */

    /**
     * Generates the asset path structure by replacing placeholders with actual values.
     *
     * @param  string $location  The asset location (e.g., 'admin', 'editor', 'site').
     * @param  string $extension The asset file extension (e.g., 'js', 'css').
     *
     * @return string The generated asset path structure.
     */
    private function getAssetPathStructure(string $location, string $extension): string {
        $pref = $this->getPreference('assets_path_structure');

        $pref = Str::replace('{extension}', $extension, $pref);
        $pref = Str::replace('{location}', $location, $pref);

        return $pref;
    }

    /**
     * Returns the path to an asset's (possible) config file.
     * Used to retrieve paths for config and dependancy file candidates when discovering assets.
     *
     * @param  array  $pathInfo
     * @param  string $fileName e.g. 'config' or 'index.asset.php' for dependancies.
     *
     * @return string
     */
    private function getAssetConfigPath(array $pathInfo, string $fileName): string {
        return trailingslashit($pathInfo['dirname']) . $fileName . '.php';
    }

    /**
     * Returns array of asset objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only assets that are ready.
     *
     * @return Collection
     */
    final public function getAssets(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->registry::get('assets')
                    ->where('source', $this)
                    ->where('ready', true) ?? collect([]);
        } else {
            return $this->registry::get('assets')
                    ->where('source', $this) ?? collect([]);
        }
    }

    /**
     * Returns a specific asset object registered by the item.
     *
     * @param  string $handle The handle of the asset to return.
     * 
     * @return Asset|null
     */
    final public function getAsset(string $handle): Asset|null {
        $asset = $this->getAssets()->firstWhere('handle', $handle);

        return $asset ?: null;
    }
}