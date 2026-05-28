<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

final class Asset extends FeatureDefinition {
    /**
     * The unique handle for the asset, used as the identifier when enqueuing in WordPress.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The file path to the asset on the server, used for versioning and validation.
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
     * A human-readable label for the asset.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * A description for the asset, which can be used in the admin UI or for documentation purposes.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The type of the asset, which determines how it will be enqueued in WordPress.
     *
     * @var string
     */
    protected string $type = '';

    /**
     * An instance of the asset's group if set.
     *
     * @var AssetGroup|null
     */
    protected ?AssetGroup $group = null;

    /**
     * The location where the asset should be enqueued, which determines the WordPress hook it will be attached to.
     *
     * @var string
     */
    protected string $location = '';

    /**
     * An array of handles for any dependencies that this asset has, which will be enqueued before this asset.
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
     * Whether to enqueue the asset in the site footer. Only applicable for 'script' type assets.
     *
     * @var boolean
     */
    protected bool $inFooter = false;

    /**
     * The WordPress hook that the asset is attached to for enqueuing.
     *
     * @var string
     */
    protected string $hook = '';

    /**
     * Maps the asset's location to a WordPress hook
     * for enqueuing.
     *
     * @var array
     */
    private array $hookMapping = [
        'admin'  => 'admin_enqueue_scripts',
        'editor' => 'enqueue_block_editor_assets',
        'site'   => 'wp_enqueue_scripts',
    ];

    /**
     * Validates the asset's configuration and sets the hook for enqueuing in the correct WordPress location.
     *
     * @return boolean True if the asset is ready to be enqueued, false otherwise.
     */
    protected function isReady(): bool {
        $requiredConfig = ['handle', 'type', 'location', 'src'];
        
        foreach ($requiredConfig as $configKey) {
            if (empty($this->$configKey)) {
                return false;
            }
        }

        $hook = $this->hook !== '' 
            ? $this->hook 
            : ($this->hookMapping[$this->location] ?? '');

        if (empty($hook)) {
            return false;
        }

        // Fix for styles in the block editor.
        if ($this->type === 'style') {
            $hook = $hook === 'enqueue_block_editor_assets' ? 'enqueue_block_assets' : $hook;
        }

        $this->hook = $hook;

        return true;
    }

    /**
     * Queues the asset with the appropriate WordPress hook for enqueuing when the asset is ready.
     * 
     * @return void
     */
    protected function queue(): void {
        if ($this->group !== null) {
            return; // Asset will be queued by the group via the groupQueue() method.
        }

        if (!$this->isReady()) {
            return; // Don't queue the asset until it's ready to avoid hooking into WordPress with incomplete configuration.
        }

        if (!$this->queued) {
            add_action($this->hook, function() {
                $this->enqueue();
            });
        }

        $this->queued = true;
    }

    /**
     * Queues the asset when it's part of a group by hooking it into WordPress.
     * This method is called by the AssetGroup when queuing its assets.
     * 
     * @return void
     */
    public function groupQueue(): void {
        if ($this->group === null) {
            return; // This method should only be called by an AssetGroup when queuing its assets
        }

        if (!$this->isReady()) {
            return; // Don't queue the asset if it's not ready.
        }

        if (!$this->queued) {
            add_action($this->hook, function() {
                $this->enqueue();
            });
        }

        $this->queued = true;
    }

    /**
     * Loads the asset by hooking it into WordPress.
     *
     * @return void
     */
    protected function enqueue(): void {
        if ($this->type === 'script') {
            wp_enqueue_script(
                $this->handle,
                $this->src,
                $this->dependencies,
                $this->version,
                $this->inFooter
            );

        } 
        
        elseif ($this->type === 'style') {
            wp_enqueue_style(
                $this->handle,
                $this->src,
                $this->dependencies,
                $this->version
            );
        }
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the source of the asset by the given URL or file path.
     *
     * @param  Closure|string $srcPathOrClosure
     * @param  boolean $inFooter
     *
     * @return self
     * @throws \InvalidArgumentException if the file does not exist at the given path or if the file type is invalid.
     */
    public function script(Closure|string $srcPathOrClosure, bool $inFooter = false): self {
        if ($srcPathOrClosure instanceof Closure) {
            $srcPathOrClosure($this);
            return $this;
        }

        $srcOrPath = $srcPathOrClosure;
        $path      = $srcOrPath;
        $src       = $srcOrPath; 

        // Convert to path if URL provided
        if (Str::startsWith($srcOrPath, ['http://', 'https://', '//'])) {
            $path = Str::replace($this->provider->uri, $this->provider->path, $srcOrPath);
        }

        else {
            $src = Str::replace($this->provider->path, $this->provider->uri, $srcOrPath);
        }

        if (!File::exists($path)) {
            throw new \InvalidArgumentException("Asset file not found at path: {$path}");
        }

        $extension = Str::afterLast($path, '.');

        if (!in_array($extension, ['js', 'css'])) {
            throw new \InvalidArgumentException("Invalid asset file type '{$extension}' for asset with path: {$path}. Allowed file types are: js, css.");
        }

        $this->type = $extension === 'js' ? 'script' : 'style';
        $this->path = $path;

        return $this->src($src)
                    ->location('site') // Default location, can be overridden by calling location() method
                    ->inFooter($inFooter)
                    ->version(filemtime($path));
    }

    /**
     * Alias for script() method to allow for more fluent code when registering styles.
     *
     * @param  Closure|string $srcOrPath
     *
     * @return self
     * @throws \InvalidArgumentException if the file does not exist at the given path or if the file type is invalid.
     */
    public function style(Closure|string $srcOrPath): self {
        return $this->script($srcOrPath);
    }

    /**
     * Set the handle for the asset, which is used as the unique identifier when enqueuing.
     *
     * @param  string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $handle = Str::slug($handle);
        $this->handle = $handle;

        $this->queue();
        return $this;
    }

    /**
     * Sets the path for the asset, which can be used to automatically generate the src and version if not set.
     *
     * @param string $path
     *
     * @return self
     * @throws \InvalidArgumentException if the file does not exist at the given path or if the file type is invalid.
     */
    public function path(string $path): self {
        if (!File::exists($path)) {
            throw new \InvalidArgumentException("Asset file not found at path: {$path}");
        }

        if (!in_array(Str::afterLast($path, '.'), ['js', 'css'])) {
            throw new \InvalidArgumentException("Invalid asset file type for asset with path: {$path}. Allowed file types are: js, css.");
        }

        $this->path = $path;

        if (empty($this->version)) {
            $this->version = filemtime($path);
        }
        
        if (empty($this->src)) {
            $this->src = Str::replace($this->provider->path, $this->provider->uri, $path);
        }

        if (empty($this->type)) {
            $extension = Str::afterLast($path, '.');
            $this->setType($extension === 'js' ? 'script' : 'style');
        }

        $this->queue();
        return $this;
    }

    /**
     * Sets the source of the asset by the given URL.
     *
     * @param  string  $src
     *
     * @return self
     * @throws \InvalidArgumentException if the file does not exist at the given path or if the file type is invalid.
     */
    public function src(string $src): self {
        if (!in_array(Str::afterLast($src, '.'), ['js', 'css'])) {
            throw new \InvalidArgumentException("Invalid asset file type for asset with src: {$src}. Allowed file types are: js, css.");
        }

        $this->src = $src;

        if (empty($this->path)) {
            $this->path = Str::replace($this->provider->uri, $this->provider->path, $src);
        }

        if (empty($this->version) && File::exists($this->path)) {
            $this->version = filemtime($this->path);
        }

        if (empty($this->type)) {
            $extension = Str::afterLast($src, '.');
            $this->setType($extension === 'js' ? 'script' : 'style');
        }

        $this->queue();
        return $this;
    }

    /**
     * Set a group for the asset, which can be used to group related assets together for switching.
     *
     * @param  AssetGroup $group
     *
     * @return self
     */
    public function group(AssetGroup $group): self {
        $this->group = $group;
        return $this;
    }

    // Convenience methods for allowed locations
    public function inAdmin(): self {
        return $this->location('admin');
    }

    public function inEditor(): self {
        return $this->location('editor');
    }

    public function inSite(): self {
        return $this->location('site');
    }

    /**
     * Sets the location for the asset, which determines where it will be enqueued in WordPress.
     * Allowed locations are defined in the $hookMapping property.
     * 
     * This method also sets the corresponding WordPress hook for enqueuing the asset based on the location.
     *
     * @param  string $location
     *
     * @return self
     * @throws \InvalidArgumentException if the location is not valid.
     */
    public function location(string $location): self {
        if (!in_array($location, array_keys($this->hookMapping))) {
            throw new \InvalidArgumentException("Invalid location '{$location}' for asset. Allowed locations are: " . implode(', ', array_keys($this->hookMapping)) . ".");
        }

        else {
            $this->location = $location;
        }

        $this->hook = $this->hookMapping[ $location ];

        $this->queue();
        return $this;
    }

    /**
     * Sets any dependencies for the asset, which will be enqueued before this asset.
     *
     * @param  array $dependencies
     *
     * @return self
     */
    public function dependencies(array $dependencies): self {
        $this->dependencies = $dependencies;
        return $this;
    }

    /**
     * Sets the version for the asset, which can be used for cache busting.
     * 
     * By default this class uses the file modification time if the path 
     * is set and the file exists.
     *
     * @param  string $version
     *
     * @return self
     */
    public function version(string $version): self {
        $this->version = $version;
        
        return $this;
    }

    /**
     * Sets whether to enqueue the asset in the site footer. Only used for 'script' type assets.
     *
     * @param  boolean $inFooter
     *
     * @return self
     */
    public function inFooter(bool $inFooter = true): self {
        $this->inFooter = $inFooter;

        return $this;
    }

    /**
     * Sets a human-readable label for the asset, which can be used in the admin UI.
     *
     * @param  string $label
     *
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        
        return $this;
    }

    /**
     * Sets a description for the asset, which can be used in the admin UI.
     *
     * @param  string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;

        return $this;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Sets the type of the asset, which determines how it will be enqueued in WordPress.
     * Allowed types are 'script' and 'style'.
     *
     * @param  string $type
     * 
     * @return void
     * @throws \InvalidArgumentException if the type is not valid.
     */
    protected function setType(string $type): void {
        if (!in_array($type, ['script', 'style'])) {
            throw new \InvalidArgumentException("Invalid asset type '{$type}'. Allowed types are: script, style.");
        }

        $this->type = $type;
    }
}

