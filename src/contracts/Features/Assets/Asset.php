<?php 

namespace MM\Meros\Contracts\Features\Assets;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Concerns\ResolvesPaths;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Facades\Assets\AssetGroups;

abstract class Asset extends Feature implements Registrable, Makeable {
    /**
     * The unique handle for the asset, used as the identifier when enqueuing in WordPress.
     * Currently set to the class's identifier and will likely be deprecated in the future.
     *
     * @var string
     */
    protected string $handle = '';
    
    /**
     * The full file path to the asset on the server, used for versioning and validation.
     *
     * @var string
     */
    protected string $path = '';
    
    /**
     * The source URL for the asset, which is used when enqueuing in WordPress.
     *
     * @var string
     */
    protected string $src = '';

    /**
     * An array of handles for any dependencies that this asset has.
     *
     * @var array
     */
    protected array $dependencies = [];

    /**
     * The version of the asset
     *
     * @var string
     */
    protected string $version = '';

    /**
     * The area of the site where the asset should be loaded. 
     * Can be either 'frontend,' 'admin' or 'editor'.
     *
     * @var string
     */
    protected string $area = 'frontend';

    /**
     * Indicates that the register() method has been called on the asset.
     *
     * @var boolean
     */
    protected bool $preRegistered = false;

    /**
     * Indicates whether the asset has been registered with WordPress.
     *
     * @var bool
     */
    protected bool $isRegistered = false;

    /**
     * Indicates that the enqueue() method has been called on the asset.
     *
     * @var boolean
     */
    protected bool $preEnqueued = false;

    /**
     * Indicates whether the asset has been enqueued with WordPress.
     *
     * @var bool
     */
    protected bool $isEnqueued = false;

    /**
     * An instance of the asset's group if set.
     *
     * @var AssetGroup|null
     */
    protected ?AssetGroup $group = null;

    use ResolvesPaths, IsRegistrable, IsMakeable, InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('handle', 'slug');
    }

    protected function whenConfigured(): void {
        if (isset($this->passedProps['path']) && is_string($this->passedProps['path'])) {
            $handle = isset($this->passedProps['handle']) && is_string($this->passedProps['handle']) && !empty($this->passedProps['handle'])
                ? $this->passedProps['handle']
                : '';

            $dependencies = isset($this->passedProps['dependencies']) && is_array($this->passedProps['dependencies'])
                ? $this->passedProps['dependencies']
                : [];

            $this->registerFromPath($this->passedProps['path'], $handle, $dependencies);
        }

        if (!empty($this->dependencies)) {
            $this->initialiseDependencyGroups();
        }

        if (empty($this->handle) && !empty($this->path)) {
            $this->handle = $this->generateHandleFromPath();
        }
    }

    /**
     * Initialises any dependency groups that are specified in the asset's dependencies.
     * This method looks for dependencies that start with 'group-' and attempts to resolve them to an AssetGroup instance,
     * converting the group dependency into the individual asset handles of the group.
     *
     * @return void
     */
    private function initialiseDependencyGroups(): void {
        foreach ($this->dependencies as $dependency) {
            if (Str::startsWith($dependency, 'group_')) {
                $groupName = Str::after($dependency, 'group_');
                $group     = AssetGroups::get($groupName);

                if (!($group instanceof AssetGroup)) {
                    unset($this->dependencies[array_search($dependency, $this->dependencies)]);
                    continue;
                }

                $area = $this->area;

                $groupDependencies = $this instanceof Script
                    ? $group->getScriptHandles($area)
                    : ($this instanceof Style ? $group->getStyleHandles($area) : []);

                unset($this->dependencies[array_search($dependency, $this->dependencies)]);
                $this->dependencies = array_merge($this->dependencies, $groupDependencies);
            }
        }
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * Registers the asset with WordPress. Implementing classes should ensure the $isRegistered 
     * property is set to true after successful registration.
     * 
     * For internal use only. Use the register() method for public registration.
     *
     * @return void
     */
    abstract public function __registerAsset(): void;

    /**
     * Enqueues the asset with WordPress. Implementing classes should ensure the $isEnqueued 
     * property is set to true after successful enqueuing.
     * 
     * For internal use only. Use the enqueue() method for public enqueuing.
     *
     * @return void
     */
    abstract public function __enqueueAsset(): void;

    /**
     * Registers the asset with WordPress. This method should be called to make the asset available for use.
     * Implementing classes should ensure assets aren't registered multiple times using the $isRegistered property.
     *
     * @return static
     */
    abstract public function register(): static;

    /**
     * Enqueues the asset with WordPress. This method should be called to include the asset in the page.
     * Implementing classes should ensure assets aren't enqueued multiple times using the $isEnqueued property.
     *
     * @return static
     */
    abstract public function enqueue(): static;

    /**
     * Resolves the appropriate WordPress hook for registering the script based on the specified area.
     *
     * @return string
     */
    final protected function resolveRegisterHook(): string {
        switch ($this->area) {
            case 'admin':
                return 'admin_init';
            case 'editor':
                return 'admin_init';
            default:
                return 'init';
        }
    }

    /**
     * Resolves the appropriate WordPress hook for enqueuing the script based on the specified area.
     *
     * @return string
     */
    final protected function resolveEnqueueHook(): string {
        switch ($this->area) {
            case 'admin':
                return 'admin_enqueue_scripts';
            case 'editor':
                return $this instanceof Style 
                    ? 'enqueue_block_assets' 
                    : 'enqueue_block_editor_assets';
            default:
                return 'wp_enqueue_scripts';
        }
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the handle for the asset, which is used as the identifier when enqueuing in WordPress.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function handle(string $handle): static {
        return $this->setIdentifier($handle, false);
    }

    /**
     * Sets the asset's file path.
     *
     * @param string $path
     * @param bool   $configure Optional. Whether to configure the asset from the path (sets src and version). Default is false.
     *
     * @return static
     */
    final public function path(string $path, bool $configure = false): static {
        if ($configure) {
            $this->configureFromPath($path);
        } else {
            $this->path = $path;
        }

        return $this;
    }

    /**
     * Sets the asset's source URL.
     *
     * @param string $src
     *
     * @return static
     */
    final public function src(string $src): static {
        $this->src = $src;
        return $this;
    }

    /**
     * Sets the asset's dependencies.
     *
     * @param array $dependencies
     *
     * @return static
     */
    final public function dependencies(array $dependencies): static {
        $this->dependencies = $dependencies;
        return $this;
    }

    /**
     * Adds a single dependency to the asset's dependencies.
     *
     * @param string $dependency
     *
     * @return static
     */
    final public function dependancy(string $dependency): static {
        if (!in_array($dependency, $this->dependencies)) {
            $this->dependencies[] = $dependency;
        }

        return $this;
    }

    /**
     * Sets the asset's version.
     *
     * @param string $version
     *
     * @return static
     */
    final public function version(string $version): static {
        $this->version = $version;
        return $this;
    }

    /**
     * Adds the asset to an asset group, which can be used to group related assets together for switching in wp-admin.
     *
     * @param  AssetGroup|string $group An existing AssetGroup instance or the name/class of a registered asset group.
     *
     * @return static
     */
    final public function group(AssetGroup|string $group): static {
        if (is_string($group)) {
            $group = $this->makeItemFrom($group, AssetGroup::class);

            if (!$group instanceof AssetGroup) {
                throw new \InvalidArgumentException("The provided group '{$group}' is not a valid AssetGroup instance.");
            }
        }
        
        $group->add($this);
        $this->group = $group;
        return $this;
    }

    /**
     * Configures an asset using the provided file path, setting the path, source URL, and version based on the file's properties.
     * Registers the asset with WordPress when configured.
     *
     * @param string $path The file path to the asset (relative to the provider's assets path or absolute).
     * @param array  $handleOrDependencies Optional. The handle of the asset or an array of dependencies.
     * @param array  $dependencies Optional. An array of dependencies for the asset.
     *
     * @return static
     */
    final public function registerFromPath(string $path, string|array $handleOrDependencies = [], array $dependencies = []): static {
        $this->registerOrEnqueueFromPath(true, $path, $handleOrDependencies, $dependencies);
        return $this;
    }

    /**
     * Configures an asset using the provided file path, setting the path, source URL, and version based on the file's properties.
     * Enqueues the asset with WordPress when configured.
     *
     * @param string $path The file path to the asset (relative to the provider's assets path or absolute).
     * @param array  $handleOrDependencies Optional. The handle of the asset or an array of dependencies.
     * @param array  $dependencies Optional. An array of dependencies for the asset.
     *
     * @return static
     */
    final public function enqueueFromPath(string $path, string|array $handleOrDependencies = [], array $dependencies = []): static {
        $this->registerOrEnqueueFromPath(false, $path, $handleOrDependencies, $dependencies);
        return $this;
    }

    /**
     * Registers or enqueues the asset based on the provided path, handle, and dependencies.
     *
     * @param bool         $register Whether to register (true) or enqueue (false) the asset.
     * @param string       $path The file path of the asset.
     * @param string|array $handleOrDependencies Optional. The handle of the asset or an array of dependencies.
     * @param array        $dependencies Optional. An array of dependencies for the asset.
     *
     * @return void
     */
    private function registerOrEnqueueFromPath(
        bool         $register, 
        string       $path, 
        string|array $handleOrDependencies = [], 
        array        $dependencies = []
    ): void {
        $this->configureFromPath($path);

        $handle = '';

        if (is_string($handleOrDependencies)) {
            $handle = $handleOrDependencies;
        } elseif (is_array($handleOrDependencies)) {
            $dependencies = $handleOrDependencies;
        }

        if (!empty($handle)) {
            $this->handle($handle);
        } else {
            $this->handle($this->generateHandleFromPath($this->path));
        }

        $this->dependencies($dependencies);

        if ($register) {
            $this->register();
        } else {
            $this->enqueue();
        }
    }

    /**
     * Configures an asset using the provided file path, setting the path, source URL, 
     * and version based on the file's properties.
     *
     * @param string $path
     *
     * @return void
     */
    private function configureFromPath(string $path): void {
        $this->path    = $this->resolveAssetPath($path);
        $this->src     = $this->convertPathToUri($this->path);
        $this->version = $this->generateVersionFromPath($this->path);
    }

    /**
     * Resolves the asset's path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string $path
     *
     * @return string
     * @throws \InvalidArgumentException if the resolved path does not point to a valid file, or if the file is not a valid JS or CSS file.
     */
    private function resolveAssetPath(string $path): string {
        if ($this->pathLooksAbsolute($path) && 
            $this->pathIsFile($path) &&
            $this->fileHasExtensions($path, ['js', 'css'], true)
        ) {
            return $path;
        }

        $provider = $this->getProvider();
        $providerAssetsPath = $provider->getPreference('assets_path');

        $path = rtrim($providerAssetsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        if (!$this->pathIsFile($path)) {
            throw new \InvalidArgumentException("The provided path '{$path}' does not point to a valid file.");
        }

        // Validate the file extension (throws error on failure)
        $this->fileHasExtensions($path, ['js', 'css'], true);

        return $path;
    }

    /**
     * Generates an asset handle based on the provided file path.
     *
     * @param string $path
     *
     * @return string
     */
    final protected function generateHandleFromPath(string $path = ''): string {
        if (empty($path)) {
            $path = $this->path;
        }

        if (!$this->pathIsFile($path)) {
            throw new \InvalidArgumentException("The provided path '{$path}' does not point to a valid file.");
        }

        $type = $this instanceof Script ? 'script' : ($this instanceof Style ? 'style' : 'style');

        // Remove the file extension
        $path = Str::replace('.' . File::extension($path), '', $path);

        $provider = $this->getProvider();
        $providerHandle = Str::slug(Str::replace('_', '-', $provider->getHandle()));
        $providerAssetsPath = $provider->getPreference('assets_path');

        if (Str::contains($path, $providerAssetsPath)) {
            $relativePath = ltrim(Str::after($path, $providerAssetsPath), DIRECTORY_SEPARATOR);
            $snake = Str::slug(Str::replace([DIRECTORY_SEPARATOR, '_', '.'], '-', $relativePath));

            return "{$providerHandle}_{$type}_{$snake}";
        } 
        
        else {
            $basename = pathinfo($path, PATHINFO_FILENAME);
            $snake = Str::slug(Str::replace(['-', '_', '.'], '-', $basename));
            return "{$providerHandle}_{$type}_{$snake}";
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the handle of the asset.
     *
     * @return string
     */
    final public function getHandle(): string {
        return $this->handle;
    }

    /**
     * Returns whether the asset is part of a group.
     *
     * @return boolean
     */
    final public function isGrouped(): bool {
        return $this->group !== null;
    }

    /**
     * Returns the asset's area context.
     *
     * @return string
     */
    final public function getArea(): string {
        return $this->area;
    }

    /**
     * Returns the type of the asset, either 'script' or 'style'.
     *
     * @return string
     */
    final public function getType(): string {
        return $this instanceof Script ? 'script' : ($this instanceof Style ? 'style' : 'style');
    }
}

