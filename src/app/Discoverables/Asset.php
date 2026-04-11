<?php 

namespace MM\Meros\App\Discoverables;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Feature;

final class Asset extends Feature {
    public string $path = '';
    public string $src  = '';
    public string $handle = '';
    public string $label = '';
    public string $description = '';
    public string $type = '';
    public string $group = '';
    public string $location = '';
    public array  $dependencies = [];
    public array  $conditions = [];
    public string $version = '';
    public bool   $inFooter = false;

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

    public function __construct(public FeatureProvider $source) {
        $this->addToRegistry();
    }

    /**
     * Sets the asset as ready (or not) based on the asset's current configuration.
     * 
     * @return void
     */
    protected function setReady(): void {
        $requiredConfig = ['handle', 'type', 'location', 'path', 'src'];
        
        foreach ($requiredConfig as $configKey) {
            if (empty($this->$configKey)) {
                $this->ready = false;
                return;
            }
        }

        $this->ready = true;

        $requiredConfigForSwitch = ['label', 'description'];

        foreach ($requiredConfigForSwitch as $configKey) {
            if (empty($this->$configKey)) {
                $this->isSwitchable = false;
                return;
            }
        }

        $this->isSwitchable = $this->source->getPreference('assets_are_switchable_by_default');
    }

    /**
     * Sets the WordPress hook used to enqueue the asset.
     *
     * @return void
     */
    protected function setHook(): void {
        if (empty($this->location) || empty($this->type)) {
            $this->error = "Cannot set hook for asset because 'location' or 'type' is not set.";
            $this->ready = false;
            return;
        }

        $hook = $this->hookMapping[$this->location];

        if ($this->type === 'style') {
            // Fix for styles in the block editor.
            $hook = $hook === 'enqueue_block_editor_assets' ? 'enqueue_block_assets' : $hook;
        }

        $this->hook = $hook;
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

        // Check conditions before enqueuing the asset.
        foreach ($instance->condiditions ?? [] as $condition) {
            if (is_callable($condition) && !call_user_func($condition)) {
                return; // If any condition fails, do not enqueue the asset.
            }
        }

        if ($instance->type === 'script') {
            wp_enqueue_script(
                $instance->handle,
                $instance->src,
                $instance->dependencies ?? [],
                $instance->version ?? filemtime($instance->path),
                $instance->inFooter ?? false
            );

        } 
        
        elseif ($instance->type === 'style') {
            wp_enqueue_style(
                $instance->handle,
                $instance->src,
                $instance->dependencies ?? [],
                $instance->version ?? filemtime($instance->path)
            );
        }

        $instance->loaded = true; // Mark the asset as loaded after enqueuing.
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the source of the asset by the given URL or file path.
     * Will attempt to auto-configure other properties based on the source if possible.
     *
     * @param  string  $srcOrPath
     * @param  boolean $inFooter
     *
     * @return self
     */
    public function script(string $srcOrPath, bool $inFooter = false): self {
        $path = $srcOrPath;

        // Convert to path if URL provided
        if (Str::startsWith($srcOrPath, ['http://', 'https://', '//'])) {
            $path = Str::replace($this->source->uri, $this->source->path, $srcOrPath);
        }

        return $this->configure($path)->inFooter($inFooter);
    }

    /**
     * Alias for script() method to allow for more fluent code when registering styles.
     *
     * @param  string $srcOrPath
     *
     * @return self
     */
    public function style(string $srcOrPath): self {
        return $this->script($srcOrPath);
    }

    /**
     * Sets the source of the asset by the given URL.
     *
     * @param  string  $src
     *
     * @return self
     */
    public function src(string $src): self {
        $this->src = $src;

        // Determine the type based on the file extension if possible.
        $ext = Str::afterLast($src, '.');

        if (!in_array($ext, ['js', 'css'])) {
            $this->error = "Invalid asset file type '{$ext}' for asset with src: {$src}. Allowed file types are: js, css.";
        }

        else {
            $this->type($ext === 'js' ? 'script' : 'style');
        }

        return $this;
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

        return $this;
    }

    /**
     * Sets the location for the asset, which determines where it will be enqueued in WordPress.
     * Allowed locations are defined in the $hookMapping property.
     * 
     * May be auto-configured by the configure() method.
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

        return $this;
    }

    /**
     * Sets any dependencies for the asset, which will be enqueued before this asset.
     * 
     * May be auto-configured by the configure() method if a dependencies.php 
     * file is present in the asset's directory.
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
     * Sets any conditions for the asset, which will be checked before enqueuing the asset.
     * Conditions should be an array of callables that return true if the asset should be loaded.
     * 
     * May be auto-configured by the configure() method if a conditions.php 
     * file is present in the asset's directory.
     *
     * @param  array $conditions
     *
     * @return self
     */
    public function conditions(array $conditions): self {
        $this->conditions = $conditions;

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
     * May be auto-configured by the configure() method if a config.php 
     * file with a 'label' key is present in the asset's directory or group directory.
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
     * May be auto-configured by the configure() method if a config.php 
     * file with a 'description' key is present in the asset's directory or group directory.
     *
     * @param  string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;

        return $this;
    }

    /**
     * Enqueue the asset by hooking it into WordPress at the appropriate time based on its location.
     * Should be the final method called in the chain when configuring the asset.
     *
     * @return self
     */
    public function enqueue(): self {
        $this->setReady();

        if (! $this->ready) {
            $this->error = "Cannot enqueue asset because it is not ready. Please ensure all required configuration is set. Last error: {$this->error}";
            return $this;
        }

        $this->setHook();

        add_action($this->hook, function() {
            $this->load($this);
        });

        return $this;
    }

    /******************************
     * Protected Chainable methods
     ******************************/

    /**
     * Sets the source of the asset by the given file path and attempts 
     * to auto-configure other properties based on the path if possible.
     * 
     * If configuration is not possible users may call other chainable 
     * methods to set the required properties manually.
     *
     * @param  string  $path
     *
     * @return self
     */
    protected function configure(string $path): self {
        $sourceAssetsPath = trailingslashit($this->source->getPreference('assets_path'));

        // Check we're handling a full path
        if (!Str::startsWith($path, $sourceAssetsPath)) {
            $path = $sourceAssetsPath . ltrim($path, '/');
        }

        // Check the file exists
        if (!File::exists($path) || !File::isFile($path)) {
            $this->error = "The asset file does not exist at the provided path: {$path}";
            
            return $this;
        }

        // Set vars
        $pathInfo     = pathinfo($path);
        $locationPath = $pathInfo['dirname']; // Should always be a location if Meros bundler is used. e.g. /path/to/assets/admin or /path/to/assets/{group}/admin
        $location     = basename($locationPath);

        // Set the path in the config
        $this->path = $path;
        
        // Set the src in the config
        $src = Str::replace($this->source->path, $this->source->uri, $path);
        $this->src($src); // Also validates & sets the type

        // Bail if we have an error at this stage, likely from the src() method.
        if ($this->error !== '') {
            return $this;
        }

        /**
         * Determine the location, group and handle if possible
         * 
         * This will only work if the asset is located in a directory that 
         * follows the expected structure e.g:
         * 
         * /assets/build/{location}/asset.js or /assets/build/{group}/{location}/asset.js 
         */
        if (in_array($location, array_keys($this->hookMapping))) {
            $this->location($location);
            
            $nonGroupDirNames = array_merge(
                array_keys($this->hookMapping), [
                    'assets', 
                    'resources', 
                    'dist', 
                    'build', 
                    'public', 
                    'src'
                ]);
            
            $possibleGroup = basename(dirname($locationPath));
            $group = !in_array($possibleGroup, $nonGroupDirNames) 
                ? $possibleGroup
                : $location;

            $this->group($group);

            // Generate a handle if not already set.
            if (empty($this->handle)) {
                $baseHandle = Str::slug($this->source->name);
                $handle     = $baseHandle . '-' . ($group !== '' ? $group : '') . '-' . $location . '-' . $this->type;
                $handle     = Str::replace('--', '-', $handle); // Clean up any double dashes.
                $this->handle($handle);
            }

            // See if there's any other config we can set based on the file path
            $locationFiles = File::files($locationPath);
            foreach ($locationFiles as $configFile) {
                $ext      = $configFile->getExtension();
                $fileName = $configFile->getFilenameWithoutExtension();

                if ($ext !== 'php') {
                    continue; // Only look for PHP config files.
                }

                if ($fileName === 'conditions') {
                    $conditions = include $configFile->getPathname();
                    if (is_array($conditions)) {
                        $this->conditions($conditions);
                    }
                }

                if ($fileName === 'dependencies') {
                    $dependencies = include $configFile->getPathname();
                    if (is_array($dependencies)) {
                        $this->dependencies($dependencies);
                    }
                }

                if ($fileName === 'config') {
                    $config = include $configFile->getPathname();
                    if (is_array($config)) {
                        $label = $config['label'] ?? '';
                        $desc  = $config['description'] ?? '';

                        $this->label($label);
                        $this->description($desc);
                    }
                }
            }

            $groupFiles = $group !== '' ? File::files(dirname($locationPath)) : [];
            foreach ($groupFiles as $configFile) {
                $ext      = $configFile->getExtension();
                $fileName = $configFile->getFilenameWithoutExtension();

                if ($ext !== 'php') {
                    continue; // Only look for PHP config files.
                }

                if ($fileName === 'config') {
                    $config = include $configFile->getPathname();
                    if (is_array($config)) {
                        $label = $config['label'] ?? '';
                        $desc  = $config['description'] ?? '';

                        $this->label($label);
                        $this->description($desc);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Sets the type for the asset, which determines how it will be enqueued in WordPress.
     * Allowed types are 'script' and 'style'.
     *
     * @param  string $type
     *
     * @return self
     */
    protected function type(string $type): self {
        $this->type = $type;
;
        return $this;
    }

    /**
     * Set a group for the asset, which can be used to group related assets together in the admin UI.
     * 
     * May be auto-configured by the path() method if the asset is located in a subdirectory 
     * of the assets directory. e.g. /assets/{group}/admin/asset.js
     *
     * @param  string  $group
     *
     * @return void
     */
    protected function group(string $group): void {
        $this->group = $group;
    }
}

