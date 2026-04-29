<?php 

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Services\Asset;
use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Discovery;

class Assets extends Register implements Discovery {
    protected string $identifier = 'handle';
    protected string $definition = Asset::class;

    /**
     * List of supported operations for this register.
     *
     * @var array
     */
    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'get',
        'all',
        'attach'
    ];

    use Concerns\Discovers;

    /**
     * Discovers assets in the specified path and registers them.
     *
     * @param string|null $path The path to discover assets from. If null, the provider's default assets path will be used.
     *
     * @return void
     */ 
    public function discover(?string $path = null): void {
        $this->ensureCheckedOut();
        $this->discoverAssets($path);
        $this->checkin(); // Check the register back in after discovery
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
            'group'        => $props['group'] ?? '',
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
            $base   = Str::slug($this->provider->getName()) . '-';
            $handle = $base . ($groupIsLocation ? '' : $group . '-') . $location . '-' . $type;

            // Generate label if not set in config
            if ($label === '') {
                $label = Str::title(Str::replace(['-', '_'], ' ', $location)) . ' ' . ($type === 'script' ? 'Script' : 'Style');
            }

            // Set the src
            $src = Str::replace($this->provider->getPath(), $this->provider->getUri(), $path);

            // Create and register the asset
            $this->make([
                'path'         => $path,
                'src'          => $src,
                'handle'       => $handle,
                'label'        => $label,
                'description'  => $desc,
                'type'         => $type,
                'group'        => $group,
                'location'     => $location,
                'version'      => filemtime($path), // Use file modification time as version for cache busting
                'dependencies' => $dependencies,
            ]);

            $this->checkout($checkedOutTo); // Checkout the register for the next iteration
        }
    }
}