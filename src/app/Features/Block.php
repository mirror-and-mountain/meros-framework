<?php 

namespace MM\Meros\App\Features;

use Illuminate\Support\Str;

use MM\Meros\App\Contracts\BlocksRegistrar;
use MM\Meros\App\Features\Concerns\MakesSwitch;

use MM\Meros\App\Facades\Registry;

class Block extends Feature {
    /**
     * The name of the block in provider/name format
     *
     * @var string
     */
    public string $name;

    /**
     * The human-readable label for the block.
     *
     * @var string
     */
    public string $label;

    /**
     * A description of the block.
     *
     * @var string
     */
    public string $description;

    /**
     * The path to the block's main file, relative to the registrar's base path.
     *
     * @var string
     */
    public string $path;
    
    /**
     * Whether the block is enabled by default.
     *
     * @var bool
     */
    public bool $enabled;

    use MakesSwitch;

    public function __construct(
        public BlocksRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates a Block instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the block.
     * 
     * @return self  An instance of the Block feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->name         = $sanitizedConfig['name'];

            $this->handle = $sanitizedConfig['handle'] !== '' 
                ? $sanitizedConfig['handle'] 
                : Str::replace(['/', '-'], '_', $this->name);

            $this->label        = $sanitizedConfig['label'];
            $this->description  = $sanitizedConfig['description'];
            $this->path         = $sanitizedConfig['path'];
            $this->enabled      = $sanitizedConfig['enabled'];
            $this->isSwitchable = $sanitizedConfig['is_switchable'];

            $this->ready = true;

            if ($this->isSwitchable) {
                $this->makeSwitch();
            }

            // Queue the block for registration on the 'init' hook
            add_action('init', [$this, 'load']);
        }

        Registry::add('blocks', $this);

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

