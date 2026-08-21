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
     * Whether to register assets in the group when the group is enabled instead of enqueuing them.
     *
     * @var boolean
     */
    protected bool $registerWhenEnabled = false;


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
            $valid = is_string($class) && class_exists($class) && is_subclass_of($class, Asset::class);
            $alias = is_string($alias) ? $alias : '';

            if (!$valid) {
                unset($this->assets[$alias]);
                continue;
            }

            $this->assets[$alias] = $this->makeItemFrom($alias !== '' ? $alias : $class, Asset::class);
        }
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
                $handle       = $maybeHandle;
                $dependencies = [];

                if (is_string($config)) {
                    $path = $config;
                } elseif (is_array($config) && isset($config['path']) && is_string($config['path'])) {
                    $path         = $config['path'];
                    $dependencies = isset($config['dependencies']) && is_array($config['dependencies']) ? $config['dependencies'] : [];
                    $handle       = isset($config['handle']) && is_string($config['handle']) && !empty($config['handle']) ? $config['handle'] : '';
                } else {
                    continue;
                }

                $type = Str::endsWith($path, '.js') 
                    ? 'script' 
                    : (Str::endsWith($path, '.css') ? 'style' : null);

                if ($type === null) {
                    continue;
                }

                $class = match ($type) {
                    'script' => match ($key) {
                        'site'   => Script::class,
                        'admin'  => AdminScript::class,
                        'editor' => EditorScript::class,
                        default  => null,
                    },
                    'style' => match ($key) {
                        'site'   => Style::class,
                        'admin'  => AdminStyle::class,
                        'editor' => EditorStyle::class,
                        default  => null,
                    },
                };

                if ($class === null) {
                    continue;
                }

                $props = ['path' => $path, 'dependencies' => $dependencies];

                if (is_string($handle) && !empty($handle)) {
                    $props['handle'] = $handle;
                }

                $assets[] = $this->makeItem($class, $props);
            }
        }

        if (!empty($assets)) {
            $this->assets = $assets;
        }
    }

    /**
     * Runs if the asset group is enabled, enqueuing all assets in the group.
     *
     * @return void
     */
    final protected function whenEnabled(): void {
        if (empty($this->assets)) {
            return;
        }

        foreach ($this->assets as $asset) {
            if ($this->registerWhenEnabled) {
                $asset->register();
            } else {
                $asset->enqueue();
            }
        }
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
     * Adds an asset to the group.
     *
     * @param Asset $asset
     *
     * @return static
     */
    public function add(Asset $asset): static {
        $this->assets[] = $asset;
        return $this;
    }

    /**
     * Sets whether to register assets in the group when the group is enabled instead of enqueuing them.
     *
     * @param boolean $register
     *
     * @return static
     */
    public function registerWhenEnabled(bool $register = true): static {
        $this->registerWhenEnabled = $register;
        return $this;
    }

    /**
     * Sets whether to enqueue assets in the group when the group is enabled instead of registering them.
     *
     * @param boolean $enqueue
     *
     * @return static
     */
    public function enqueueWhenEnabled(bool $enqueue = true): static {
        $this->registerWhenEnabled = !$enqueue;
        return $this;
    }

    /**
     * Sets assets for the group. Can be used in implementing classes to define the assets that belong to the group.
     * 
     * Each asset can be an instance of Asset, a class name, or an associative array with a key representing the type (site, admin, editor) and a value representing the path.
     * 
     * Class Name Example: MyAsset::class
     * Location & Path Example: ['site' => ['path/to/asset.js', 'path/to/asset.css'], 'admin' => ['path/to/admin-asset.js']]
     *
     * @param array $assets
     *
     * @return static
     */
    final protected function assets(array $assets): static {
        $this->assets = $assets;
        return $this;
    }

    /**
     * Sets assets for the admin context of the group.
     *
     * @param array $assets
     *
     * @return static
     */
    final protected function adminAssets(array $assets): static {
        $this->assets['admin'] = $assets;
        return $this;
    }

    /**
     * Sets assets for the site context of the group.
     *
     * @param array $assets
     *
     * @return static
     */
    final protected function siteAssets(array $assets): static {
        $this->assets['site'] = $assets;
        return $this;
    }

    /**
     * Sets assets for the editor context of the group.
     *
     * @param array $assets
     *
     * @return static
     */
    final protected function editorAssets(array $assets): static {
        $this->assets['editor'] = $assets;
        return $this;
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
            $area   = $area === 'site' ? 'frontend' : $area;
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