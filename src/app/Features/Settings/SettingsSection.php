<?php 

namespace MM\Meros\App\Features\Settings;

use Closure;

use MM\Meros\App\Features\Feature;
use MM\Meros\App\Contracts\SettingsRegistrar;

use MM\Meros\App\Facades\Registry;

class SettingsSection extends Feature {
    /**
     * The human-readable title of the settings section.
     *
     * @var string
     */
    public string $title;

    /**
     * The handle of the settings page this section belongs to.
     *
     * @var string
     */
    public string $page;

    /**
     * An array of additional arguments compatible with the args parameter of add_settings_section.
     *
     * @var array
     */
    public array $args;

    /**
     * The callback used to render the settings section.
     *
     * @var Closure
     */
    public Closure $callback;

    public function __construct(
        public SettingsRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates a SettingsSection instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the settings section.
     * 
     * @return self  An instance of the SettingsSection feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->handle = $sanitizedConfig['id'];
            $this->title  = $sanitizedConfig['title'];
            $this->page   = $sanitizedConfig['page'];
            $this->args   = $sanitizedConfig['args'];

            $this->callback = $this->convertToClosure($sanitizedConfig['callback']);

            $this->ready = true;

            // Hook the load method to the admin_init action to register the settings section
            add_action('admin_init', [$this, 'load']);
        }

        Registry::add('settingsSections', $this);

        return $this;
    }

    /**
     * Set the configuration schema for the settings section.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'id'         => ['type' => 'string', 'required' => true],
            'title'      => ['type' => 'string', 'required' => false, 'default' => ''],
            'page'       => ['type' => 'string', 'required' => true],
            'callback'   => ['type' => 'callable|closure', 'required' => true],
            'args'       => ['type' => 'array', 'required' => false, 'default' => []],
        ];
    }

    /**
     * Adds the settings section to the specified settings page.
     *
     * @return void
     */
    final public function load(): void {
        add_settings_section(
            $this->handle,
            $this->title,
            $this->callback,
            $this->page,
            $this->args
        );

        $this->loaded = true;
    }
}