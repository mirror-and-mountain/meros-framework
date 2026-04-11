<?php 

namespace MM\Meros\App\Discoverables;

use Illuminate\Support\Str;
use MM\Meros\App\Support\Feature;


class Block extends Feature {
    /**
     * The name of the block in provider/name format
     *
     * @var string
     */
    public string $name = '';

    /**
     * The human-readable label for the block.
     *
     * @var string
     */
    public string $label = '';

    /**
     * A description of the block.
     *
     * @var string
     */
    public string $description = '';

    /**
     * The path to the block's main file, relative to the registrar's base path.
     *
     * @var string
     */
    public string $path = '';
    
    /**
     * Whether the block is enabled by default.
     *
     * @var bool
     */
    public bool $enabled = false;

    public function __construct(
        public BlocksRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates a Block instance from a config array or handle and registers it.
     *
     * @param  array|string $configOrHandle Configuration array for the block or a handle string.
     * 
     * @return self  An instance of the Block feature.
     */
    public function make(array|string $configOrHandle): self {
        if (is_array($configOrHandle)) {        
            $sanitizedConfig = $this->sanitizeConfig($configOrHandle);
            if ($sanitizedConfig !== false) {
                $this->setPropertiesFromConfig($sanitizedConfig);

                $this->handle = $sanitizedConfig['handle'] !== '' 
                    ? $sanitizedConfig['handle'] 
                    : Str::replace(['/', '-'], '_', $this->name);

                $this->ready = true;
                $this->register();
            }
        }

        else if (is_string($configOrHandle)) {
            $this->handle = $configOrHandle;
        }

        Registry::add('blocks', $this);

        return $this;
    }

    /***************************
     * Chainable methods
     ***************************/

    public function path(string $path): self {
        if (!file_exists($path)) {
            $this->error = "The specified path '{$path}' does not exist.";
            $this->ready = false;
            return $this;
        }
        
        $this->path = $path;
        $this->setReady();
        return $this;
    }

    public function label(string $label): self {
        $this->label = $label;
        
        $this->setReady();
        return $this;
    }

    public function description(string $description): self {
        $this->description = $description;

        $this->setReady();
        return $this;
    }

    /**
     * Registers the block by hooking it into WordPress.
     * Should be used as the final chainable method after setting up the block's properties, or will be called automatically if the block is made with a config array.
     *
     * @return self
     */
    public function register(): self {
        if ($this->isSwitchable) {
            $this->makeSwitch();
        }

        if ($this->enabled) {
            add_action('init', [$this, 'load']);
        }
        
        return $this;
    }

    /**
     * Set the configuration schema for the block.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'name'          => ['type' => 'string', 'required' => true],
            'path'          => ['type' => 'string', 'required' => true],
            'handle'        => ['type' => 'string', 'required' => false, 'default' => ''],
            'label'         => ['type' => 'string', 'required' => false, 'default' => ''],
            'description'   => ['type' => 'string', 'required' => false, 'default' => ''],
            'enabled'       => ['type' => 'boolean', 'required' => false, 'default' => true],
            'is_switchable' => ['type' => 'boolean', 'required' => false, 'default' => true],
        ];
    }

    /**
     * Sets the block as ready (or not) based on the state of its properties.
     * @return void
     */
    protected function setReady(): void {
        $propsSet = !empty($this->name) && !empty($this->path) && empty($this->error);
        $this->ready = $propsSet;
    }

    /**
     * Registers the block type with WordPress.
     *
     * @return void
     */
    public function load(): void {
        if (!$this->ready || !$this->enabled) {
            return;
        }

        $block = register_block_type($this->path);

        if ($block !== false) {
            $this->loaded = true;
        }
    }
}

