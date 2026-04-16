<?php 

namespace MM\Meros\App\Support;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\FeatureProvider;

final class Asset extends Feature {
    public bool   $enabled      = true;
    public string $path         = '';
    public string $src          = '';
    public string $handle       = '';
    public string $label        = '';
    public string $description  = '';
    public string $type         = '';
    public string $group        = '';
    public string $location     = '';
    public array  $dependencies = [];
    public string $version      = '';
    public bool   $inFooter     = false;

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

    public function __construct(public FeatureProvider $source) {}

    /**
     * Sets the asset as ready (or not) based on the asset's current configuration.
     * Adds the asset to the appropriate WordPress hook for loading when the asset is ready.
     * 
     * @return void
     */
    protected function setReady(): void {
        $requiredConfig = ['handle', 'type', 'location', 'src', 'hook'];
        
        foreach ($requiredConfig as $configKey) {
            if (empty($this->$configKey)) {
                $this->ready = false;
                return;
            }
        }

        $this->ready = true;

        $hook = $this->hook;

        // Fix for styles in the block editor.
        if ($this->type === 'style') {
            $hook = $hook === 'enqueue_block_editor_assets' ? 'enqueue_block_assets' : $hook;
        }

        add_action($hook, function() {
            $this->load($this);
        });
    }

    /**
     * Loads the asset by hooking it into WordPress.
     * 
     * @param Feature $instance The instance of the asset to load.
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        if (!$instance->ready || !$instance->enabled) {
            return;
        }

        if ($instance->type === 'script') {
            wp_enqueue_script(
                $instance->handle,
                $instance->src,
                $instance->dependencies ?? [],
                $instance->version,
                $instance->inFooter ?? false
            );

        } 
        
        elseif ($instance->type === 'style') {
            wp_enqueue_style(
                $instance->handle,
                $instance->src,
                $instance->dependencies ?? [],
                $instance->version
            );
        }

        $instance->loaded = true; // Mark the asset as loaded after enqueuing.
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
            $path = Str::replace($this->source->uri, $this->source->path, $srcOrPath);
        }

        else {
            $src = Str::replace($this->source->path, $this->source->uri, $srcOrPath);
        }

        if (!File::exists($path)) {
            $this->error = "Asset file not found at path: {$path}";
            return $this;
        }

        $extension = Str::afterLast($path, '.');

        if (!in_array($extension, ['js', 'css'])) {
            $this->error = "Invalid asset file type '{$extension}' for asset with path: {$path}. Allowed file types are: js, css.";
            return $this;
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

        $this->setReady();
        return $this;
    }

    public function path(string $path): self {
        if (!File::exists($path)) {
            $this->error = "Asset file not found at path: {$path}";
            return $this;
        }

        if (!in_array(Str::afterLast($path, '.'), ['js', 'css'])) {
            $this->error = "Invalid asset file type for asset with path: {$path}. Allowed file types are: js, css.";
            return $this;
        }

        $this->path = $path;

        if (empty($this->version)) {
            $this->version = filemtime($path);
        }
        
        if (empty($this->src)) {
            $this->src = Str::replace($this->source->path, $this->source->uri, $path);
        }

        if (empty($this->type)) {
            $extension = Str::afterLast($path, '.');
            $this->setType($extension === 'js' ? 'script' : 'style');
        }

        $this->setReady();
        return $this;
    }

    /**
     * Sets the source of the asset by the given URL.
     *
     * @param  string  $src
     *
     * @return self
     */
    public function src(string $src): self {
        if (!in_array(Str::afterLast($src, '.'), ['js', 'css'])) {
            $this->error = "Invalid asset file type for asset with src: {$src}. Allowed file types are: js, css.";
            return $this;
        }

        $this->src = $src;

        if (empty($this->path)) {
            $this->path = Str::replace($this->source->uri, $this->source->path, $src);
        }

        if (empty($this->version) && File::exists($this->path)) {
            $this->version = filemtime($this->path);
        }

        if (empty($this->type)) {
            $extension = Str::afterLast($src, '.');
            $this->setType($extension === 'js' ? 'script' : 'style');
        }

        $this->setReady();
        return $this;
    }

    /**
     * Set a group for the asset, which can be used to group related assets together in the admin UI.
     *
     * @param  string  $group
     *
     * @return self
     */
    public function group(string $group): self {
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
     */
    public function location(string $location): self {
        if (!in_array($location, array_keys($this->hookMapping))) {
            $this->error = "Invalid location '{$location}' for asset. Allowed locations are: " . implode(', ', array_keys($this->hookMapping)) . ".";
        }

        else {
            $this->location = $location;
        }

        $this->hook = $this->hookMapping[ $location ];

        $this->setReady();
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
     */
    protected function setType(string $type): void {
        if (!in_array($type, ['script', 'style'])) {
            $this->error = "Invalid asset type '{$type}'. Allowed types are: script, style.";
            return;
        }

        $this->type = $type;
    }
}

