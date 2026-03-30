<?php 

namespace MM\Meros\App\Features;

use MM\Meros\App\Contracts\AssetsRegistrar;
use MM\Meros\App\Features\Concerns\MakesSwitch;

use MM\Meros\App\Facades\Registry;

class Asset extends Feature {
    /**
     * The type of asset, either 'js' or 'css'.
     *
     * @var string
     */
    public string $type;

    /**
     * The location where the asset should be enqueued, either 'admin', 'editor', or 'site'.
     *
     * @var string
     */
    public string $location;

    /**
     * The asset group if applicable.
     *
     * @var string
     */
    public string $group;

    /**
     * The human-readable label for the asset.
     * Required only for switchable assets.
     *
     * @var string
     */
    public string $label;

    /**
     * A description of the asset.
     * Required only for switchable assets.
     *
     * @var string
     */
    public string $description;

    /**
     * The path to the asset file, relative to the registrar's base path.
     *
     * @var string
     */
    public string $path;

    /**
     * The URL to the asset file, relative to the registrar's base URI.
     *
     * @var string
     */
    public string $src;

    /**
     * Conditions that must be met for the asset to be enqueued.
     *
     * @var array
     */
    public array $conditions;

    /**
     * The asset's dependencies.
     *
     * @var array
     */
    public array $dependencies;

    /**
     * Whether the asset is enabled by default.
     *
     * @var bool
     */
    public bool $enabled;

    /**
     * Whether the asset should be loaded in the footer.
     *
     * @var bool
     */
    public bool $inFooter;

    /**
     * Maps the assets location to a WordPress hook
     * for enqueuing.
     *
     * @var array
     */
    private array $hookMapping = [
        'admin'  => 'admin_enqueue_scripts',
        'editor' => 'enqueue_block_editor_assets',
        'site'   => 'wp_enqueue_scripts',
    ];

    use MakesSwitch;

    public function __construct(
        public AssetsRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates an Asset instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the asset.
     * 
     * @return self  An instance of the Asset feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->handle       = $sanitizedConfig['handle'];
            $this->type         = $sanitizedConfig['type'];
            $this->location     = $sanitizedConfig['location'];
            $this->group        = $sanitizedConfig['group'];
            $this->label        = $sanitizedConfig['label'];
            $this->description  = $sanitizedConfig['description'];

            $this->path         = $sanitizedConfig['path'];
            $this->src          = $sanitizedConfig['src'];

            $this->conditions   = $sanitizedConfig['conditions'];
            $this->dependencies = $sanitizedConfig['dependencies'];

            $this->enabled      = $sanitizedConfig['enabled'];
            $this->isSwitchable = $sanitizedConfig['is_switchable'];
            $this->inFooter     = $sanitizedConfig['in_footer'];

            $this->ready = true;

            if ($this->isSwitchable) {
                $this->makeSwitch();
            }

            if ($this->enabled) {
                $hook = $this->hookMapping[$this->location];

                if ($this->type === 'css') {
                    // Fix for styles in the block editor.
                    $hook = $hook === 'enqueue_block_editor_assets' ? 'enqueue_block_assets' : $hook;
                }

                // Hook the load method.
                add_action($hook, [$this, 'load']);
            }

        }

        Registry::add('assets', $this);

        return $this;
    }

    /**
     * Set the configuration schema for the asset.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'handle'        => ['type' => 'string', 'required' => true],
            'type'          => ['type' => 'string', 'required' => true, 'allowed_values' => ['js', 'css']],
            'location'      => ['type' => 'string', 'required' => true, 'allowed_values' => ['admin', 'editor', 'site']],
            'group'         => ['type' => 'string', 'required' => false, 'default' => ''],
            'label'         => ['type' => 'string', 'required' => false, 'default' => ''],
            'description'   => ['type' => 'string', 'required' => false, 'default' => ''],
            'path'          => ['type' => 'string', 'required' => true],
            'src'           => ['type' => 'string', 'required' => true],
            'conditions'    => ['type' => 'array',  'required' => false, 'default' => []],
            'dependencies'  => ['type' => 'array',  'required' => false, 'default' => []],
            'enabled'       => ['type' => 'boolean',  'required' => false, 'default' => true],
            'is_switchable' => ['type' => 'boolean',  'required' => false, 'default' => false],
            'in_footer'     => ['type' => 'boolean',  'required' => false, 'default' => false],
        ];
    }

    public function load(): void {
        if (!$this->ready || !$this->enabled) {
            return;
        }

        // Check conditions before enqueuing the asset.
        foreach ($this->conditions as $condition) {
            if (is_callable($condition) && !call_user_func($condition)) {
                return; // If any condition fails, do not enqueue the asset.
            }
        }

        if ($this->type === 'js') {
            wp_enqueue_script(
                $this->handle,
                $this->src,
                $this->dependencies,
                filemtime($this->path),
                $this->inFooter
            );
        } elseif ($this->type === 'css') {
            wp_enqueue_style(
                $this->handle,
                $this->src,
                [],
                filemtime($this->path)
            );
        }

        $this->loaded = true; // Mark the asset as loaded after enqueuing.
    }
}

