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

        $assetDirectories = File::directories($assetsPath);

        if ($assetDirectories === []) {
            return; // No asset directories found
        }

        $groups = [];

        foreach ($assetDirectories as $directory) {
            $dirName = basename($directory);

            // Handle grouped assets
            if (! in_array($dirName, $this->assetLocations)) {
                $group = $this->initAssetGroup($dirName, $directory);
                $groups[ $dirName ] = $group;
            }

            else {
                // Handle ungrouped assets
                $group = $this->initAssetLocation([
                    'name'        => $this->handle . '_' . $dirName,
                    'label'       => '',
                    'description' => '',
                    'switchable'  => false,
                ], $directory);

                $groups[ $dirName ] = $group;
            }
        }

        foreach ($groups as $group => $groupConfig) {
            $isLocation = in_array($group, $this->assetLocations);
            
            if ($isLocation) {
                $assets = ['script' => $groupConfig['script'] ?? null, 'style' => $groupConfig['style'] ?? null];
            } 
            
            else {
                // For grouped assets, iterate through each location within the group
                foreach ($this->assetLocations as $location) {
                    if (!isset($groupConfig[$location])) {
                        continue;
                    }
                    
                    $locationAssets = [
                        'script' => $groupConfig[$location]['script'] ?? null,
                        'style'  => $groupConfig[$location]['style'] ?? null
                    ];
                    
                    $this->processAssets($locationAssets, $groupConfig, $group, $location, true);
                }
                continue;
            }
            
            $this->processAssets($assets, $groupConfig, $group, $group, false);
        }

            
            
        }
    
        /**
         * Processes assets for a group or location.
         *
         * @param  array  $assets       The assets to process.
         * @param  array  $groupConfig  The group configuration.
         * @param  string $group        The group name.
         * @param  string $location     The location name.
         * @param  bool   $isGrouped    Whether this is a grouped asset.
         * 
         * @return void
         */
        private function processAssets(array $assets, array $groupConfig, string $group, string $location, bool $isGrouped): void {
            $isLocation = in_array($group, $this->assetLocations);
            
            foreach ($assets as $type => $assetPath) {
                if ($assetPath === null) continue;
    
                $handle       = $groupConfig['name'] . '_' . (!Str::endsWith($groupConfig['name'], $location) ? $location . '_' : '') . $type;
                $typeKey      = $type === 'script' ? 'script' : 'style';
                $inFooter     = $type === 'script' ? apply_filters($handle . '_asset_in_footer', false) : false;
                $isSwitchable = $groupConfig['switchable'] ?? false;
    
                $enabled = $this->getPreference('assets_are_enabled_by_default');
    
                if ($isSwitchable) {
                    $setting = $this->handle . '_' . $group . '_asset_enable';
                    $enabled = (bool) get_option($setting, $enabled);
                } else {
                    // Filter enabled for this asset
                    $enabled = apply_filters($handle . '_asset_is_enabled', $enabled);
                }
                
                $locationKey = $isGrouped ? $location : $group;
                $conditions = $isLocation 
                    ? ($groupConfig['conditions'] ?? [])
                    : ($groupConfig[$locationKey]['conditions'] ?? []);
                $dependencies = $isLocation
                    ? (($groupConfig['dependencies'] ?? [])[$typeKey] ?? [])
                    : (($groupConfig[$locationKey]['dependencies'] ?? [])[$typeKey] ?? []);
                
                $assetConfig = [
                    'handle'        => $handle,
                    'type'          => $type === 'script' ? 'js' : 'css',
                    'location'      => $location,
                    'group'         => $group,
                    'is_switchable' => $isSwitchable,
                    'path'          => $assetPath,
                    'src'           => Str::replace($this->path, $this->uri, $assetPath),
                    'label'         => $groupConfig['label'],
                    'description'   => $groupConfig['description'],
                    'conditions'    => $conditions,
                    'dependencies'  => $dependencies,
                    'in_footer'     => $inFooter,
                    'enabled'       => $enabled,
                ];
    
                $this->makeAsset($assetConfig);
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
     * Initialises an asset group by scanning the given directory and its subdirectories for assets and config.
     *
     * @param  string $group          The name of the asset group.
     * @param  string $assetDirectory The path to the asset group directory.
     * 
     * @return array The initialised asset group configuration.
     */
    private function initAssetGroup(string $group, string $assetDirectory): array {
        // Set the initial group properties
        $groupConfig = [
            'name'        => $this->handle . '_' . $group,
            'label'       => '',
            'description' => '',
            'switchable'  => false,
        ];

        // Get user config for the group if available and merge with default group config
        $groupConfig = array_merge($groupConfig, $this->getUserConfig($assetDirectory));
        
        // Look for 'location' named subdirectories
        $locationDirs = File::directories($assetDirectory);

        foreach ($locationDirs as $locationDir) {
            $groupConfig = $this->initAssetLocation($groupConfig, $locationDir, true);
        }

        return $groupConfig;
    }

    /**
     * Initialises an asset location by scanning the given directory for assets and config.
     *
     * @param  array  $assetGroup        The current asset group configuration to be updated with the location config.
     * @param  string $locationDirectory The path to the asset location directory.
     * @param  bool   $grouped           Whether the location is part of a group (i.e. in a subdirectory named after the group).
     * 
     * @return array The updated asset group configuration with the location config added.
     */
    private function initAssetLocation(array $assetGroup, string $locationDirectory, bool $grouped = false): array {
        $location = basename($locationDirectory);

        if (! in_array($location, $this->assetLocations)) {
            return $assetGroup; // Not a valid location directory
        }

        // Look for asset files in the location directory
        $assets = File::files($locationDirectory);
        
        if ($assets === []) {
            return $assetGroup; // No files found in location directory
        }

        // Get user config for the location if available and merge with default group config (if not already merged via group config)
        $assetGroup = array_merge($assetGroup, $this->getUserConfig($locationDirectory));

        if ($grouped) {
            // Initialise the dependencies array for this location
            $assetGroup[ $location ]['dependencies'] = [
                'style'  => [],
                'script' => []
            ];
        }

        foreach ($assets as $asset) {
            $extension = $asset->getExtension();
            $fileName  = $asset->getFilenameWithoutExtension();

            if (! in_array($extension, ['js', 'css', 'php'])) {
                continue; // Not a valid asset file
            }

            // Check for WordPress generated dependencies
            if ($fileName === 'index.asset' && $extension === 'php') {
                $dependencies = include $asset->getPathname();
                if (is_array($dependencies)) {
                    if ($grouped) {
                        $assetGroup[ $location ]['dependencies']['script']  = $dependencies['dependencies'] ?? [];
                    }

                    else {
                        $assetGroup['dependencies']['script']  = $dependencies['dependencies'] ?? [];
                    }
                }
            }

            // Check for user-defined dependencies (these will be prioritised over WP generated dependencies if both are present)
            if ($fileName === 'dependencies' && $extension === 'php') {
                $dependencies = include $asset->getPathname();
                if (is_array($dependencies)) {
                    if ($grouped) {
                        $assetGroup[ $location ]['dependencies']['style']  = $dependencies['style'] ?? [];
                        $assetGroup[ $location ]['dependencies']['script'] = $dependencies['script'] ?? []; 
                    }

                    else {
                        $assetGroup['dependencies']['style']  = $dependencies['style'] ?? [];
                        $assetGroup['dependencies']['script'] = $dependencies['script'] ?? []; 
                    }
                }
            }

            // Check for user-defined conditions
            if ($fileName === 'conditions' && $extension === 'php') {
                $conditions = include $asset->getPathname();
                if (is_array($conditions)) {
                    if ($grouped) {
                        $assetGroup[ $location ]['conditions'] = $conditions;
                    }

                    else {
                        $assetGroup['conditions'] = $conditions;
                    }
                }
            }

            // Check for the root script file
            if ($fileName === 'index' && $extension === 'js') {
                if ($grouped) {
                    $assetGroup[ $location ]['script'] = $asset->getPathname();
                }

                else {
                    $assetGroup['script'] = $asset->getPathname();
                }
            }

            // Check for the root style file
            if ($fileName === 'style-index' && $extension === 'css') {
                if ($grouped) {
                    $assetGroup[ $location ]['style'] = $asset->getPathname();
                }

                else {
                    $assetGroup['style'] = $asset->getPathname();
                }
            }
        }
        
        return $assetGroup;
    }

    /**
     * Retrieves user config for a given asset directory if available and sanitises it.
     *
     * @param  string $directory
     *
     * @return array
     */
    private function getUserConfig(string $directory): array {
        $configPath      = trailingslashit($directory) . 'config.php';
        $config          = File::exists($configPath) ? include $configPath : null;
        $sanitizedConfig = [];

        if (is_array($config)) {
            $switchableByDefault = $this->getPreference('assets_are_switchable_by_default');

            $label       = $config['label'] ?? '';
            $description = $config['description'] ?? '';
            $switchable  = $label !== '' && $description !== '' && $switchableByDefault ? true : false;

            $sanitizedConfig['label']       = $label;
            $sanitizedConfig['description'] = $description;
            $sanitizedConfig['switchable']  = $switchable;
        }

        return $sanitizedConfig;
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
            return $this->registry->get('assets')
                    ->where('source', $this)
                    ->where('ready', true) ?? collect([]);
        } else {
            return $this->registry->get('assets')
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