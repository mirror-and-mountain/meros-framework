<?php 

namespace MM\Meros\Contracts\Features\Assets;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Concerns\IsSwitchable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;

use MM\Meros\Contracts\Features\Concerns\MakesItems;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Facades\Framework;

class AssetGroup extends Feature implements Registrable, Makeable {
    /**
     * The unique name for the asset group.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The action to perform when the asset group is enabled. Can be empty, 'register' or 'enqueue'.
     *
     * @var string
     */
    protected string $whenEnabledAction = '';

    /**
     * An array of Asset instances, class names or paths to be included in the asset group. 
     * These assets will be enqueued when the group is enabled.
     *
     * @var array<Asset|string>
     */
    protected array $assets = [];

    use IsMakeable, IsRegistrable, IsSwitchable, MakesItems, InstantiatesItems;

    // =========================================================================
    // Initialisation / Switching
    // =========================================================================

    final protected function init(): void {
        $this->identifier('name', 'snake');
    }

    /**
     * Resolves the settings container for the asset group, which holds its switch setting.
     *
     * @param SettingsContainers $register
     *
     * @return SettingsContainer
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $container = $register->get('meros_asset_group_settings', Framework::get());

        if ($container === null) {
            $container = $register->checkout(Framework::get())->makeFrom('meros_asset_group_settings');
        }

        if (!($container instanceof SettingsContainer)) {
            throw new \LogicException("The resolved settings container must be an instance of SettingsContainer.");
        }

        return $container;
    }

    /**
     * Attempts to instantiate any assets that are provided as class names or paths, 
     * converting them into Asset instances.
     *
     * @return void
     */
    final protected function beforeSwitchInit(): void {
        if (empty($this->assets)) {
            return;
        }

        $firstAsset = reset($this->assets);

        if ($firstAsset instanceof Asset) {
            return;
        }

        $maybeArrayOfClasses = is_string($firstAsset) && Str::contains($firstAsset, '\\');

        if ($maybeArrayOfClasses) {
            $this->instantiateAssetsFromClasses();
            return;
        }

        $maybeArrayOfPaths = is_array($firstAsset);

        if ($maybeArrayOfPaths) {
            $this->instantiateAssetsFromPaths();
        }
            
    }

    /**
     * Attempts to instantiate any assets that are provided as class names, converting them into Asset instances.
     *
     * @return void
     */
    private function instantiateAssetsFromClasses(): void {
        foreach ($this->assets as $alias => $class) {
            $stringAlias = is_string($alias) ? $alias : '';
            $asset = $this->instantiateAssetFromClass($class, $stringAlias);

            if ($asset !== null) {
                unset($this->assets[$alias]);
                $this->assets[] = $asset;
            } else {
                unset($this->assets[$alias]);
            }
        }
    }

    /**
     * Attempts to instantiate the given asset class, returning an Asset instance if successful.
     *
     * @param string $class
     * @param string $alias
     *
     * @return Asset|null
     */
    private function instantiateAssetFromClass(string $class, string $alias = ''): ?Asset {
        if (!class_exists($class) || 
            $class !== Asset::class || 
            is_subclass_of($class, Asset::class) === false
        ) {
            return null;
        }

        return $this->makeItemFrom($alias !== '' ? $alias : $class, Asset::class);
    }

    /**
     * Attempts to instantiate any assets that are provided as paths, converting them into Asset instances.
     *
     * @return void
     */
    private function instantiateAssetsFromPaths(): void {
        $assets = [];

        foreach ($this->assets as $key => $paths) {
            if (!is_string($key) || !in_array($key, ['site', 'admin', 'editor']) || !is_array($paths)) {
                unset($this->assets[$key]);
                continue;
            }

            foreach ($paths as $maybeHandle => $config) {
                $asset = $this->instantiateAssetFromPath($maybeHandle, $key, $config);

                if ($asset !== null) {
                    $assets[] = $asset;
                }
            }
        }

        if (!empty($assets)) {
            $this->assets = $assets;
        }
    }

    /**
     * Attempts to instantiate an asset from a given path, handle, and area, returning an Asset instance if successful.
     *
     * @param string|integer $maybeHandle
     * @param string         $area
     * @param string|array   $config
     *
     * @return Asset|null
     */
    private function instantiateAssetFromPath(string|int $maybeHandle, string $area, string|array $config): ?Asset {
        $handle       = $maybeHandle;
        $dependencies = [];

        if (is_string($config)) {
            $path = $config;
        } elseif (is_array($config) && isset($config['path']) && is_string($config['path'])) {
            $path         = $config['path'];
            $dependencies = isset($config['dependencies']) && is_array($config['dependencies']) ? $config['dependencies'] : [];
            $handle       = isset($config['handle']) && is_string($config['handle']) && !empty($config['handle']) ? $config['handle'] : '';
        } else {
            return null;
        }

        $type = Str::endsWith($path, '.js') 
            ? 'script' 
            : (Str::endsWith($path, '.css') ? 'style' : null);

        if ($type === null) {
            return null;
        }

        $props = [
            'path'         => $path, 
            'dependencies' => $dependencies, 
            'type'         => $type, 
            'area'         => $area
        ];

        if (is_string($handle) && !empty($handle)) {
            $props['handle'] = $handle;
        }

        return $this->makeItem(Asset::class, $props);
    }

    /**
     * Runs if the asset group is enabled, enqueuing all assets in the group.
     *
     * @return void
     */
    final protected function whenEnabled(): void {
        if (empty($this->assets) || empty($this->whenEnabledAction)) {
            return;
        }

        foreach ($this->assets as $asset) {
            if ($this->whenEnabledAction === 'register') {
                $asset->register();
            } else if ($this->whenEnabledAction === 'enqueue') {
                $asset->enqueue();
            }
        }
    }

    /**
     * Sets the group to register its assets when enabled.
     *
     * @return static
     */
    final public function register(): static {
        $this->whenEnabledAction = 'register';
        return $this;
    }

    /**
     * Sets the group to enqueue its assets when enabled.
     *
     * @return static
     */
    final public function enqueue(): static {
        $this->whenEnabledAction = 'enqueue';
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the name of the asset group. If the label is not already set, it will be automatically generated from the name.
     *
     * @param string $name
     *
     * @return static
     */
    public function name(string $name): static {
        $name = $this->setIdentifier($name);
        return $this;
    }

    /**
     * Adds an instantiated asset, uninstantiated asset, or multiple uninstantiated assets to the group.
     *
     * @param Asset|string|array $assets or $asset An instantiated Asset, a class name or path to an Asset, or an array of class names or paths to Assets.
     * @param string|array       $handleOrLocation Optional. The handle for the asset or the location(s) where the asset should be added. Can be a string or an array of locations ('site', 'admin', 'editor').
     * @param string|array       $location         Optional. The location(s) where the asset should be added. Can be a string or an array of locations ('site', 'admin', 'editor'). If provided, this will override the $handleOrLocation parameter for determining locations.
     *
     * @return static
     */
    public function add(Asset|string|array $assets, string|array $handleOrLocation = '', string|array $location = ''): static {
        if ($assets instanceof Asset) {
            $this->assets[] = $assets;
            return $this;
        }

        $locations = $this->resolveLocations($handleOrLocation, $location);
        $handle    = $this->resolveHandle($handleOrLocation);

        // Handle a single asset provided as a string, which could be a path or a class name.
        if (is_string($assets) && !empty($assets)) {
            $asset = $assets; // Could be path or class name.
            return $this->addSingle($asset, $handle, $locations);
        }

        // Handle an array of assets, which could be paths, class names, or configurations.
        if (is_array($assets)) {
            foreach ($assets as $handleOrLoc => $config) {
                $assetLocations = $this->resolveLocations($handleOrLoc, $locations);
                $assetHandle    = $this->resolveHandle($handleOrLoc);

                if (is_string($config) && !empty($config)) {
                    $asset = $config; // Could be path or class name.
                    $this->addSingle($asset, $assetHandle, $assetLocations);
                }

                else if (is_array($config)) {
                    $path = $config['path'] ?? '';

                    if (!empty($path)) {
                        $this->addSingle($config, $assetHandle, $assetLocations);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Adds a single uninstantiated asset to the group for the specified locations.
     *
     * @param string|array $classPathOrConfig
     * @param string       $handle
     * @param array        $locations
     *
     * @return static
     */
    private function addSingle(string|array $classPathOrConfig, string $handle = '', array $locations = []): static {
        if (empty($locations)) {
            $locations = ['site', 'admin', 'editor'];
        }

        foreach ($locations as $location) {
            if ($handle !== '') {
                $this->assets[$location][$handle] = $classPathOrConfig;
            } else {
                $this->assets[$location][] = $classPathOrConfig;
            }
        }

        return $this;
    }

    /**
     * Resolves locations from the provided handle or location parameters, returning an array of valid locations.
     *
     * @param string|array $handleOrLocation
     * @param string|array $location
     *
     * @return array
     */
    private function resolveLocations(string|array $handleOrLocation, string|array $location): array {
        $locations = is_array($handleOrLocation) 
            ? $handleOrLocation : (is_array($location) ? $location : null);

        if ($locations === null && is_string($handleOrLocation) && in_array($handleOrLocation, ['site', 'admin', 'editor'])) {
            $locations = [$handleOrLocation];
        }

        if ($locations === null && is_string($location) && in_array($location, ['site', 'admin', 'editor'])) {
            $locations = [$location];
        }

        return $locations ?? [];
    }

    /**
     * Resolves the handle for an asset, given a handle or location. If the input is a valid handle, it is returned; otherwise, an empty string is returned.
     *
     * @param string|array $handleOrLocation
     *
     * @return string
     */
    private function resolveHandle(string|array $handleOrLocation): string {
        if (is_string($handleOrLocation) && !in_array($handleOrLocation, ['site', 'admin', 'editor'])) {
            return $handleOrLocation;
        }

        return '';
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Gets the name of the asset group.
     * 
     * @param string $format The format of the name to return. Can be 'default', 'slug', or 'snake'. Defaults to 'default'.
     *
     * @return string
     */
    public function getName(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Gets the assets in the group.
     *
     * @param string  $area    The area to filter assets by. Can be 'site', 'admin', or 'editor'. Defaults to an empty string, which returns all assets.
     * @param string  $type    The type of assets to filter by. Can be 'script' or 'style'. Defaults to an empty string, which returns all types.
     * @param boolean $collect Whether to return a Collection instead of an array.
     *
     * @return array|Collection
     */
    public function getAssets(string $area = '', string $type = '', bool $collect = false): array|Collection {
        if ($area !== '') {
            $area   = $area === 'frontend' ? 'site' : $area;
            $assets = collect($this->assets)->filter(fn($asset) => $asset->getArea() === $area)->values();

        } else {
            $assets = collect($this->assets);
        }

        if ($type !== '' && in_array($type, ['script', 'style'])) {
            $assets = $assets->filter(fn($asset) => $asset->getType() === $type)->values();
        }

        return $collect ? $assets : $assets->toArray();
    }

    /**
     * Helper to retrieve all site assets, optionally filtered by type.
     *
     * @param string  $type
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getSiteAssets(string $type = '', bool $collect = false): array|Collection {
        return $this->getAssets('site', $type, $collect);
    }

    /**
     * Helper to retrieve all site scripts.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getSiteScripts(bool $collect = false): array|Collection {
        return $this->getAssets('site', 'script', $collect);
    }

    /**
     * Helper to retrieve all site styles.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getSiteStyles(bool $collect = false): array|Collection {
        return $this->getAssets('site', 'style', $collect);
    }

    /**
     * Helper to retrieve all admin assets, optionally filtered by type.
     *
     * @param string  $type
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getAdminAssets(string $type = '', bool $collect = false): array|Collection {
        return $this->getAssets('admin', $type, $collect);
    }

    /**
     * Helper to retrieve all admin scripts.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getAdminScripts(bool $collect = false): array|Collection {
        return $this->getAssets('admin', 'script', $collect);
    }

    /**
     * Helper to retrieve all admin styles.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getAdminStyles(bool $collect = false): array|Collection {
        return $this->getAssets('admin', 'style', $collect);
    }

    /**
     * Helper to retrieve all editor assets, optionally filtered by type.
     *
     * @param string  $type
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getEditorAssets(string $type = '', bool $collect = false): array|Collection {
        return $this->getAssets('editor', $type, $collect);
    }

    /**
     * Helper to retrieve all editor scripts.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getEditorScripts(bool $collect = false): array|Collection {
        return $this->getAssets('editor', 'script', $collect);
    }

    /**
     * Helper to retrieve all editor styles.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getEditorStyles(bool $collect = false): array|Collection {
        return $this->getAssets('editor', 'style', $collect);
    }

    /**
     * Gets the handles of all scripts in the group, optionally filtered by area.
     *
     * @param string  $area    The area to filter assets by. Can be 'site', 'admin', or 'editor'. Defaults to an empty string, which returns all assets.
     * @param boolean $collect Whether to return a Collection instead of an array.
     *
     * @return array|Collection
     */
    public function getScriptHandles(string $area = '', bool $collect = false): array|Collection {
        $assets = $this->getAssets($area, 'script', true);
        $handles = $assets->map(fn($asset) => $asset->getHandle())->values();

        return $collect ? $handles : $handles->toArray();
    }

    /**
     * Gets the handles of all styles in the group, optionally filtered by area.
     *
     * @param string  $area    The area to filter assets by. Can be 'site', 'admin', or 'editor'. Defaults to an empty string, which returns all assets.
     * @param boolean $collect Whether to return a Collection instead of an array.
     *
     * @return array|Collection
     */
    public function getStyleHandles(string $area = '', bool $collect = false): array|Collection {
        $assets = $this->getAssets($area, 'style', true);
        $handles = $assets->map(fn($asset) => $asset->getHandle())->values();

        return $collect ? $handles : $handles->toArray();
    }
}