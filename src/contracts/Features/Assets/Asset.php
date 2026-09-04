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

class Asset extends Feature implements Registrable, Makeable {
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
     * Whether the asset has been configured from a file path.
     *
     * @var boolean
     */
    private bool $configuredFromPath = false;
    
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
     * Can be either 'site,' 'admin' or 'editor', or an array of these areas for multiple contexts.
     *
     * @var string|array<string>
     */
    protected string|array $area = 'site';

    /**
     * The type of the asset, either 'script' or 'style'.
     *
     * @var string
     */
    protected string $type = '';

    /**
     * Whether the script should be loaded in the footer of the page.
     * Relevant only for script assets; ignored for styles.
     *
     * @var bool
     */
    protected bool $inFooter = false;

    /**
     * An instance of the asset's group if set.
     *
     * @var AssetGroup|null
     */
    protected ?AssetGroup $group = null;

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

    use ResolvesPaths, IsRegistrable, IsMakeable, InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('handle', 'slug');
    }

    protected function whenConfigured(): void {
        if ($this->configuredFromPath === false && 
            empty($this->path) === false
        ) {
            $this->configureFromPath($this->path);
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

                if (is_array($area)) {
                    foreach ($area as $a) {
                        $groupDependencies = $this->type === 'script'
                            ? $group->getScriptHandles($a)
                            : $group->getStyleHandles($a);

                        unset($this->dependencies[array_search($dependency, $this->dependencies)]);
                        $this->dependencies = array_merge($this->dependencies, $groupDependencies);
                    }
                } else {

                    $groupDependencies = $this->type === 'script'
                        ? $group->getScriptHandles($area)
                        : $group->getStyleHandles($area);

                    unset($this->dependencies[array_search($dependency, $this->dependencies)]);
                    $this->dependencies = array_merge($this->dependencies, $groupDependencies);
                }
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
    final public function __registerAsset(): void {
        if ($this->type === 'script') {
            $this->registerScript();
        } elseif ($this->type === 'style') {
            $this->registerStyle();
        } else {
            throw new \Exception('Invalid asset type specified for GenericAsset: ' . $this->type);
        }

        $this->isRegistered = true;
    }

    /**
     * Enqueues the asset with WordPress. Implementing classes should ensure the $isEnqueued 
     * property is set to true after successful enqueuing.
     * 
     * For internal use only. Use the enqueue() method for public enqueuing.
     *
     * @return void
     */
    final public function __enqueueAsset(): void {
        if ($this->type === 'script') {
            $this->enqueueScript();
        } elseif ($this->type === 'style') {
            $this->enqueueStyle();
        } else {
            throw new \Exception('Invalid asset type specified for GenericAsset: ' . $this->type);
        }

        $this->isEnqueued = true;
    }

    /**
     * Registers a script with WordPress.
     *
     * @return void
     */
    private function registerScript(): void {
        wp_register_script(
            $this->handle,
            $this->src,
            $this->dependencies,
            $this->version,
            $this->inFooter
        );

        $this->isRegistered = true;
    }

    /**
     * Registers a style with WordPress.
     *
     * @return void
     */
    private function registerStyle(): void {
        wp_register_style(
            $this->handle,
            $this->src,
            $this->dependencies,
            $this->version,
        );

        $this->isRegistered = true;
    }

    /**
     * Enqueues a script with WordPress.
     *
     * @return void
     */
    private function enqueueScript(): void {
        if ($this->isRegistered) {
            wp_enqueue_script($this->handle);
        } else {
            $this->__registerAsset();
            wp_enqueue_script($this->handle);
        }

        $this->isEnqueued = true;
    }

    /**
     * Enqueues a style with WordPress.
     *
     * @return void
     */
    private function enqueueStyle(): void {
        if ($this->isRegistered) {
            wp_enqueue_style($this->handle);
        } else {
            $this->__registerAsset();
            wp_enqueue_style($this->handle);
        }

        $this->isEnqueued = true;
    }

    /**
     * Registers the asset with WordPress. This method should be called to make the asset available for use.
     * Implementing classes should ensure assets aren't registered multiple times using the $isRegistered property.
     *
     * @return static
     * @throws \Exception If the asset's area is not set before registering.
     */
    final public function register(): static {
        if (empty($this->area)) {
            throw new \Exception('The area for the asset must be set before registering. Use the area() method to set it.');
        }

        if (!$this->preRegistered) {
            $hook = $this->resolveRegisterHook();

            if (is_array($hook)) {
                foreach ($hook as $h) {
                    add_action($h, [$this, '__registerAsset']);
                }
            } else {
                add_action($hook, [$this, '__registerAsset']);
            }

            $this->preRegistered = true;
        }

        return $this;
    }

    /**
     * Enqueues the asset with WordPress. This method should be called to include the asset in the page.
     * Implementing classes should ensure assets aren't enqueued multiple times using the $isEnqueued property.
     *
     * @return static
     * @throws \Exception If the asset's area is not set before enqueuing.
     */
    final public function enqueue(): static {
        if (empty($this->area)) {
            throw new \Exception('The area for the asset must be set before enqueuing. Use the area() method to set it.');
        }

        if (!$this->preEnqueued) {
            $hook = $this->resolveEnqueueHook();

            if (is_array($hook)) {
                foreach ($hook as $h) {
                    add_action($h, [$this, '__enqueueAsset']);
                }
            } else {
                add_action($hook, [$this, '__enqueueAsset']);
            }
            
            $this->preEnqueued = true;
        }

        return $this;
    }

    /**
     * Resolves the appropriate WordPress hook(s) for registering the script based on the specified area(s).
     *
     * @return string|array
     */
    final protected function resolveRegisterHook(): string|array {
        if (is_array($this->area)) {
            $hooks = [];
            foreach ($this->area as $area) {
                $hooks[] = match ($area) {
                    'admin'  => 'admin_init',
                    'editor' => 'admin_init',
                    default  => 'init',
                };
            }
            return $hooks;
        }

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
     * Resolves the appropriate WordPress hook(s) for enqueuing the script based on the specified area(s).
     *
     * @return string|array
     */
    final protected function resolveEnqueueHook(): string|array {
        if (is_array($this->area)) {
            $hooks = [];
            foreach ($this->area as $area) {
                $hooks[] = match ($area) {
                    'admin'  => 'admin_enqueue_scripts',
                    'editor' => $this->type === 'style'
                        ? 'enqueue_block_assets' 
                        : 'enqueue_block_editor_assets',
                    default  => 'wp_enqueue_scripts',
                };
            }
            return $hooks;
        }

        switch ($this->area) {
            case 'admin':
                return 'admin_enqueue_scripts';
            case 'editor':
                return $this->type === 'style'
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
     * Sets the asset's area context, which determines where the asset will be loaded in WordPress.
     *
     * @param string|array $area The area(s) where the asset should be loaded. Can be 'site', 'admin', or 'editor', or an array of these areas for multiple contexts.
     *
     * @return static
     */
    final public function area(string|array $area): static {
        $valid = function ($a) {
            return in_array($a, ['site', 'admin', 'editor']);
        };

        if (is_array($area)) {
            foreach ($area as $a) {
                if (!$valid($a)) {
                    throw new \InvalidArgumentException("Invalid area specified for asset: area = {$a}");
                }
            }
            $this->area = $area;
        } else {
            if (!$valid($area)) {
                throw new \InvalidArgumentException("Invalid area specified for asset: area = {$area}");
            }
            $this->area = $area;
        }

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
     * Sets whether the script should be loaded in the footer of the page.
     *
     * @param bool $inFooter
     *
     * @return static
     */
    final public function inFooter(bool $inFooter = true): static {
        $this->inFooter = $inFooter;

        return $this;
    }

    /**
     * Sets the type of the asset, either 'script' or 'style'.
     *
     * @param string $type
     *
     * @return static
     */
    final public function type(string $type): static {
        if (!in_array($type, ['script', 'style'])) {
            throw new \Exception('Invalid asset type specified for GenericAsset: ' . $type);
        }

        $this->type = $type;

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
     * Returns whether the asset is part of a group.
     *
     * @return boolean
     */
    final public function isGrouped(): bool {
        return $this->group !== null;
    }

    /**
     * Configures an asset using the provided file path, setting the path, source URL, 
     * and version based on the file's properties.
     *
     * @param string $path
     *
     * @return void
     */
    final protected function configureFromPath(string $path): void {
        $this->path    = $this->resolveAssetPath($path);
        $this->src     = $this->convertPathToUri($this->path);
        $this->version = $this->generateVersionFromPath($this->path);
        $this->type    = Str::endsWith($this->path, '.js') ? 'script' : 'style';

        $this->configuredFromPath = true;
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

        // See if there are any dependency files in path directory.
        if (Str::endsWith($path, '.js')) {
            $dir = dirname($path);
            $depsFile = $dir . DIRECTORY_SEPARATOR . 'index.asset.php';
            
            if (file_exists($depsFile)) {
                $deps = include $depsFile;

                if (is_array($deps) && array_key_exists('dependencies', $deps)) {
                    if (is_array($deps['dependencies']) && !empty($deps['dependencies'])) {
                        $this->dependencies = array_merge($this->dependencies, $deps['dependencies']);
                    }
                }
            }
        }

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

        $type = $this->type;

        // Remove the file extension
        $path = Str::replace('.' . File::extension($path), '', $path);

        $provider = $this->getProvider();
        $providerHandle = Str::slug(Str::replace('_', '-', $provider->getHandle()));
        $providerAssetsPath = $provider->getPreference('assets_path');

        if (Str::contains($path, $providerAssetsPath)) {
            $relativePath = ltrim(Str::after($path, $providerAssetsPath), DIRECTORY_SEPARATOR);
            $snake = Str::slug(Str::replace([DIRECTORY_SEPARATOR, '_', '.'], '-', $relativePath));

            return "{$providerHandle}-{$type}-{$snake}";
        } 
        
        else {
            $basename = pathinfo($path, PATHINFO_FILENAME);
            $snake = Str::slug(Str::replace(['-', '_', '.'], '-', $basename));
            return "{$providerHandle}-{$type}-{$snake}";
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
     * Returns the asset's area(s) context.
     *
     * @return string|array
     */
    final public function getArea(): string|array {
        return $this->area;
    }

    /**
     * Returns the type of the asset, either 'script' or 'style'.
     *
     * @return string
     */
    final public function getType(): string {
        return $this->type;
    }

    /**
     * Returns whether the asset is a script.
     *
     * @return boolean
     */
    final public function isScript(): bool {
        return $this->type === 'script';
    }

    /**
     * Returns whether the asset is a style.
     *
     * @return boolean
     */
    final public function isStyle(): bool {
        return $this->type === 'style';
    }

    /**
     * Returns whether the asset has been registered.
     *
     * @return boolean
     */
    final public function isRegistered(): bool {
        return $this->isRegistered;
    }

    /**
     * Returns whether the asset has been enqueued.
     *
     * @return boolean
     */
    final public function isEnqueued(): bool {
        return $this->isEnqueued;
    }

    /**
     * Returns whether the asset will be registered.
     *
     * @return boolean
     */
    final public function willBeRegistered(): bool {
        return $this->preRegistered;
    }

    /**
     * Returns whether the asset will be enqueued.
     *
     * @return boolean
     */
    final public function willBeEnqueued(): bool {
        return $this->preEnqueued;
    }
}

