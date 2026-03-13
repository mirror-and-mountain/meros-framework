<?php

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait HasAssets {
    /**
     * Indicates whether the item has assets.
     *
     * @var boolean
     */
    
    private bool $hasAssets = false;
    /**
     * The structure to search for assets in. This will be used
     * to generate the glob pattern for finding assets.
     * {location} and {extension} will be replaced with the
     * appropriate values.
     * 
     * @var string
     */
    protected string $assetsStructure = '/**/{location}/*.{extension}';

    /**
     * Maps assets locations/directories to Wordpress hooks.
     * Example: assets/build/admin/index.js will be enqueued using
     * admin_enqueue_scripts.
     * 
     * @var array
     */
    protected array $assetLocations = [
        'admin'  => 'admin_enqueue_scripts',
        'editor' => 'enqueue_block_editor_assets',
        'site'   => 'wp_enqueue_scripts',
    ];

    /**
     * The directory to search for assets in relative to the
     * feature directory.
     * 
     * @var string
     */
    protected string $assetsDir = 'assets/build';

    /**
     * Loaded scripts organised by location.
     * 
     * @var array
     */
    protected array $registeredScripts = [];

    /**
     * Loaded styles organised by location.
     * 
     * @var array
     */
    protected array $registeredStyles = [];

    /**
    * Whether to allow asset enabling/disabling
    * via the settings page by default.
    *
    * @var boolean
    */
    protected bool $allowAssetSwitchingByDefault = true;

    /**
     * Sets the absolute path and calls setAssets.
     *
     * @param boolean $inFooter Whether to enqueue scripts in the footer. Default false.
     * @return void
     */
    protected function loadAssets(bool $inFooter = false): void {
        $assetsPath = $this->path . $this->assetsDir;
        foreach ($this->assetLocations as $location => $_) {
            if ($this->scripts[$location] ?? [] === []) {
                $this->findAssets($assetsPath, $location, 'js', $inFooter);
            }

            if ($this->styles[$location] ?? [] === []) {
                $this->findAssets($assetsPath, $location, 'css');
            }
        }
    }

    /**
     * Uses glob to search for assets using the given path, location and extension.
     * Sets asset handles to be used in wp_enqueue functions and
     * registers assets to be enqueued.
     *
     * Will also discover any dependencies, conditions, or config for each asset.
     *
     * @param string $path
     * @param string $location
     * @param string $extension
     * @param boolean $inFooter
     * @return void
     */
    private function findAssets(
        string $path, 
        string $location, 
        string $extension, 
        bool $inFooter = false
    ): void {
        // Check the path exists
        if (! File::exists($path)) {
            return;
        }

        $assetStructure = Str::replace(['{location}', '{extension}'], [$location, $extension], $this->assetsStructure);
        $assets = File::glob($path . $assetStructure, GLOB_BRACE);

        if ($assets === []) {
            return;
        }

        $i = 0;

        foreach ($assets as $asset) {
            $pathInfo = pathinfo($asset);

            $configFile = trailingslashit(dirname($pathInfo['dirname'])) . 'config.php';
            $conditionFile = trailingslashit($pathInfo['dirname']) . 'conditions.php';
            $dependancyFile = trailingslashit($pathInfo['dirname']) . $pathInfo['filename'] . '.asset.php';

            $config = File::exists($configFile) ? include $configFile : [];
            $conditions = File::exists($conditionFile) ? include $conditionFile : [];
            $dependancies = File::exists($dependancyFile) ? include $dependancyFile : [];

            if (! is_array($config)) {
                $config = [];
            }

            if (!is_array($conditions)) {
                $conditions = [];
            }

            if (!is_array($dependancies)) {
                $dependancies = [];
            }

            $this->addAsset(
                $asset, 
                $location, 
                '', 
                $config,
                $conditions,
                $dependancies['dependencies'] ?? [],
                true,
                $this->allowAssetSwitchingByDefault,
                false,
                $inFooter,
                $i++
            );
        }
    }

    /**
     * Registers an asset to be enqueued.
     *
     * @param string $path
     * @param string $location
     * @param string $handle
     * @param array $config
     * @param array $conditions
     * @param array $dependencies
     * @param boolean $enabledByDefault
     * @param boolean $allowSwitching
     * @param boolean $isExperimental
     * @param boolean $inFooter
     * @param integer $index
     * @return void
     */
    protected function addAsset(
        string $path,
        string $location,
        string $handle = '',
        array  $config = [],
        array  $conditions = [],
        array  $dependencies = [],
        bool   $enabledByDefault = true,
        bool   $allowSwitching = false,
        bool   $isExperimental = false,
        bool   $inFooter = false,
        int    $index = 0
    ): void {
        // For switching if enabled
        $enabled = $enabledByDefault;

        // Check the asset exists
        if (! File::exists($path)) {
            $path = $this->path . trailingslashit($this->assetsDir) . $path;
            if (! File::exists($path)) {
                return;
            }
        }

        // Check the location is valid
        if (! in_array($location, array_keys($this->assetLocations))) {
            return;
        }

        // Determine asset type
        $ext = File::extension($path);
        $type = $ext === 'js' ? 'scripts' : ($ext === 'css' ? 'styles' : '');
        if ($type === '') {
            return;
        }

        // Get path info
        $pathInfo = pathinfo($path);

        // Determine handle
        if ($handle === '') {
            $handle = $this->generateHandle(
                $pathInfo, 
                $type, 
                $location, 
                $index
            );
        }

        // Set SRC
        $src = Str::replace($this->path, $this->uri, $path);

        // Create a switch if switchable
        $isSwitchable = $this->determineIsSwitchable($config);

        if ($allowSwitching && $isSwitchable) {
            $enabled = $this->createAssetSwitch(
                $config,
                $isExperimental,
                $enabledByDefault
            );
       }

        // Store the asset
        if ($type === 'scripts') {
            $this->registeredScripts[$location][$handle] = [
                'enabled'      => $enabled,
                'src'          => $src,
                'config'       => $config,
                'conditions'   => $conditions,
                'dependencies' => $dependencies,
                'version'      => filemtime($path),
                'in_footer'    => $inFooter,
            ];
        } else {
            $this->registeredStyles[$location][$handle] = [
                'enabled'      => $enabled,
                'src'          => $src,
                'config'       => $config,
                'conditions'   => $conditions,
                'dependencies' => $dependencies,
                'version'      => filemtime($path),
            ];
        }

        $this->hasAssets = true;
    }

    /**
     * Enqueues registered assets using the appropriate hooks.
     *
     * @return void
     */
    private function enqueueAssets(): void {
        foreach ($this->assetLocations as $location => $_) {
            foreach ($this->registeredScripts[$location] ?? [] as $handle => $properties) {
                $enabled = $properties['enabled'] ?? false;

                if (!$enabled) {
                    continue;
                }

                $hook     = $this->assetLocations[$location];
                $src      = $properties['src'];
                $deps     = $properties['dependencies'];
                $version  = $properties['version'];
                $inFooter = $properties['in_footer'];

                add_action($hook, function () use ($location, $handle, $src, $deps, $version, $inFooter) {
                    $shouldEnqueue = $this->shouldEnqueueAsset('scripts', $location, $handle);
                    if ($shouldEnqueue) {
                        wp_enqueue_script(
                            $handle,
                            $src,
                            $deps,
                            $version,
                            $inFooter
                        );
                    }
                });
            }

            foreach ($this->registeredStyles[$location] ?? [] as $handle => $properties) {
                $enabled = $properties['enabled'] ?? false;

                if (!$enabled) {
                    continue;
                }

                $hook    = $this->assetLocations[$location];
                $src     = $properties['src'];
                $deps    = $properties['dependencies'];
                $version = $properties['version'];

                // Fix for block editor styles
                $hook = $hook === 'enqueue_block_editor_assets' ? 'enqueue_block_assets' : $hook;
                
                add_action($hook, function () use ($location, $handle, $src, $deps, $version) {
                    $shouldEnqueue = $this->shouldEnqueueAsset('styles', $location, $handle);
                    if ($shouldEnqueue) {
                        wp_enqueue_style($handle, $src, $deps, $version);
                    }
                });
            }
        }
    }

    /**
     * Determines whether an asset can be switchable in WP Admin.
     *
     * @param array $config
     * @return boolean
     */
    private function determineIsSwitchable(array $config): bool {
        return 
            is_string($config['name'] ?? false) &&
            is_string($config['description'] ?? false);
    }

    /**
     * Creates a switch for the asset in WP Admin.
     *
     * @param array   $config
     * @param boolean $isExperimental
     * @param boolean $enabledByDefault
     * @return boolean
     */
    private function createAssetSwitch(
        array $config, 
        bool $isExperimental,
        bool $enabledByDefault = true
    ): bool {
        
        $enabled      = $enabledByDefault;
        $configName   = Str::slug($config['name'], '_');
        $hook         = $this->slug . '_' . $configName;
        $isSwitchable = apply_filters($hook . '_is_switchable', true);

        if ($isSwitchable) {
            $experimental = apply_filters($hook . '_is_experimental', $isExperimental);
        
            $settingName = $this->createSwitch(
                'asset',
                $configName,
                'theme_settings',
                'scripts_and_styles',
                $config['description'] ?? '',
                $experimental
            );

            if (is_string($settingName)) {
                $switchSetting = get_option($settingName, $enabledByDefault);
                $enabled = $switchSetting === '1' || $switchSetting === 1 || $switchSetting === true;
            }
        }

        return $enabled;
    }

    /**
     * Generates a unique handle for an asset based on its
     * path, type, location and index.
     *
     * @param array $pathInfo
     * @param string $type
     * @param string $location
     * @param integer $index
     * @return string
     */
    private function generateHandle(
        array  $pathInfo, 
        string $type, 
        string $location, 
        int    $index, 
    ): string {
        $subDir = Str::afterLast(dirname($pathInfo['dirname']), DIRECTORY_SEPARATOR);

        if ($subDir !== $location) {
            $name = $type . '_' . $subDir . '_' . Str::replace('-', '_', $pathInfo['filename']);
        } else {
            $name = $type . '_' . Str::replace('-', '_', $pathInfo['filename']);
        }

        $handle = $this->slug . '_' . $location . '_' . Str::replace('-', '_', $name) . '_' . $index;
        return $handle;
    }

    /**
     * Determines whether an asset should be enqueued based on its
     * conditions (if available).
     *
     * @param string $type
     * @param string $location
     * @param string $handle
     * @return boolean
     */
    private function shouldEnqueueAsset(string $type, string $location, string $handle): bool {
        $shouldEnqueue = true;
        $conditions = $type === 'scripts'
            ? $this->registeredScripts[$location][$handle]['conditions'] ?? []
            : $this->registeredStyles[$location][$handle]['conditions'] ?? [];

        if (is_array($conditions) && count($conditions) > 0) {
            switch ($location) {
                case 'admin':
                    $page = $_GET['page'] ?? '';
                    if (! in_array($page, $conditions)) {
                        $shouldEnqueue = false;
                    }
                    break;
                case 'site':
                    global $post;
                    if (isset($post)) {
                        $slug = $post->post_name;
                        if (! in_array($slug, $conditions)) {
                            $shouldEnqueue = false;
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $shouldEnqueue;
    }
}
