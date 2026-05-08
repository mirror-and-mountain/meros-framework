<?php 

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Services\Contracts\Asset;
use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Registers\Interfaces\Discovery;

use MM\Meros\Facades\AssetGroups;

class Assets extends Register implements Discovery {
    protected string $identifier = 'handle';
    protected string $definition = Asset::class;
    protected array  $rejects    = ['public'];

    use Concerns\Discovers;

    /**
     * Discovers assets in the specified path and registers them.
     *
     * @param string|null $path The path to discover assets from. If null, the provider's default assets path will be used.
     *
     * @return self
     */ 
    public function discover(?string $path = null): self {
        $this->ensureCheckedOut();
        $this->discoverAssets($path);
        $this->checkin(); // Check the register back in after discovery
        return $this;
    }

    /**
     * Parses properties for the asset's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'path'         => $props['path'] ?? '',
            'src'          => $props['src'] ?? '',
            'handle'       => $props['handle'] ?? '',
            'label'        => $props['label'] ?? '',
            'description'  => $props['description'] ?? '',
            'type'         => $props['type'] ?? '',
            'group'        => $props['group'] ?? null,
            'location'     => $props['location'] ?? '',
            'dependencies' => $props['dependencies'] ?? [],
            'version'      => $props['version'] ?? '',
            'inFooter'     => $props['inFooter'] ?? false,
        ];
    }

    /**
     * Discovers and registers assets for the provider based on the configured assets path.
     *
     * @param string|null $path The path to discover assets from. If null, the provider's default assets path will be used.
     *
     * @return void
     */
    protected function discoverAssets(?string $path = null): void {
        $path = $this->resolvePath($path, $this->provider->getPreference('assets_path'));

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
     * Processes asset files for a specific location and group, creating and registering Asset objects.
     *
     * @param  string  $location The location for the assets (e.g. 'admin', 'editor', 'site').
     * @param  string  $group The group for the assets, typically the name of the subdirectory they are in.
     * @param  array   $files An array of SplFileInfo objects representing the asset files to process.
     * 
     * @return void
     */
    protected function processAssetLocation(string $location, string $group, array $files): void {
        $checkedOutTo = $this->provider;

        foreach ($files as $file) {
            $path      = $file->getPathname();
            $extension = $file->getExtension();

            // Set the type
            $type = $extension === 'js' ? 'script' : 'style';

            // Set the group
            $groupIsLocation = $group === $location;

            // Check for a group config file
            $groupLabel = '';
            $groupDesc  = '';
            $switchable = $this->provider->getPreference('asset_groups_are_switchable_by_default');

            if ($location !== 'admin') {
                $groupConfig = $this->getAssetGroupConfiguration($path, $groupIsLocation);
                $groupLabel  = $groupConfig['label'] ?? '';
                $groupDesc   = $groupConfig['description'] ?? '';
                $switchable  = $groupConfig['switchable'] ?? $switchable;
            }

            // Setup an asset group if groupConfig is available
            $groupInstance = null;
            $baseName      = Str::slug($this->provider->getName()) . '-';

            if ($location !== 'admin' && $groupLabel !== '') {
                $groupName     = $baseName . $group;
                $groupInstance = AssetGroups::all(false)->firstWhere('name', $groupName);

                if (!$groupInstance) {
                    $groupInstance = AssetGroups::checkout($this->provider)
                        ->make([
                            'name'          => $groupName,
                            'label'         => $groupLabel,
                            'description'   => $groupDesc,
                            'switchable'    => $switchable,
                            'wasDiscovered' => true,
                        ]);
                }
            }


            // Generate the asset handle
            $handle = $baseName . ($groupIsLocation ? '' : $group . '-') . $location . '-' . $type;

            // Generate the asset label
            $assetLabel = Str::title(Str::replace(['-', '_'], ' ', $location)) . ' ' . ($type === 'script' ? 'Script' : 'Style');

            // Set the src
            $src = Str::replace($this->provider->getPath(), $this->provider->getUri(), $path);

            // Create and register the asset
            $asset = $this->make([
                'path'         => $path,
                'src'          => $src,
                'handle'       => $handle,
                'label'        => $assetLabel,
                'description'  => '',
                'type'         => $type,
                'group'        => $groupInstance,
                'location'     => $location,
                'version'      => filemtime($path), // Use file modification time as version for cache busting
                'dependencies' => $type === 'script' ? $this->getAssetDependencies($path) : [],
            ]);

            if ($groupInstance) {
                $groupInstance->addAsset($asset);
            }

            $this->checkout($checkedOutTo); // Checkout the register for the next iteration
        }
    }

    /**
     * Retrieves the configuration for an asset group based on the asset's path and whether the group is a location.
     *
     * @param string $assetPath The file path of the asset being processed.
     * @param bool   $groupIsLocation Indicates whether the group is a location (e.g. 'admin', 'editor', 'site') or a custom group.
     *
     * @return array An associative array containing 'label' and 'description' for the asset group, if available.
     */
    protected function getAssetGroupConfiguration(string $assetPath, bool $groupIsLocation): array {
        $config = [];

         if ($groupIsLocation) {
            $groupConfigPath = dirname($assetPath) . DIRECTORY_SEPARATOR . 'config.php'; // In the same directory for location-level config
        }

        else {
            $groupConfigPath = dirname(dirname($assetPath)) . DIRECTORY_SEPARATOR . 'config.php'; // In the parent directory for group-level config
        }

        if (File::exists($groupConfigPath)) {
            $groupConfig = include $groupConfigPath;

            if (is_array($groupConfig)) {
                $config['label']       = $groupConfig['label'] ?? '';
                $config['description'] = $groupConfig['description'] ?? '';
                $config['switchable']  = $groupConfig['switchable'] ?? null;
            }
        }

        return $config;
    }

    /**
     * Retrieves the dependencies for an asset based on a custom dependencies file or a WordPress-style asset file.
     *
     * @param string $assetPath The file path of the asset being processed.
     *
     * @return array An array of dependency handles that the asset depends on, if available.
     */
    protected function getAssetDependencies(string $assetPath): array {
        $dependencies = [];
        $wpFile = false;

        $dependencyPath = dirname($assetPath) . DIRECTORY_SEPARATOR . 'dependencies.php'; // Custom dependencies file in the same directory

        if (!File::exists($dependencyPath)) {
            $dependencyPath = dirname($assetPath) . DIRECTORY_SEPARATOR . 'index.asset.php'; // WordPress-style asset file in the same directory
            $wpFile = File::exists($dependencyPath);
        }

        if (File::exists($dependencyPath)) {
            $dependencyConfig = include $dependencyPath;

            if (is_array($dependencyConfig)) {
                $dependencies = $wpFile ? $dependencyConfig['dependencies'] ?? [] : $dependencyConfig;
            }
        }

        return $dependencies;
    }
}