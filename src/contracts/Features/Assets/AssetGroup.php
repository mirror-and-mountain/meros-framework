<?php 

namespace MM\Meros\Contracts\Features\Assets;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsSwitchable;

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
    final protected function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
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

            foreach ($paths as $path) {
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

                $assets[] = $this->makeItem($class, ['path' => $path]);
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
    final protected function runWhenEnabled(): void {
        if (empty($this->assets)) {
            return;
        }

        foreach ($this->assets as $asset) {
            $asset->enqueue();
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
     * @param boolean $collect Whether to return a Collection instead of an array.
     *
     * @return array|Collection
     */
    public function getAssets(bool $collect = false): array|Collection {
        return $collect ? collect($this->assets) : $this->assets;
    }
}