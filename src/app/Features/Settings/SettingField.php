<?php

namespace MM\Meros\App\Features\Settings;

use Closure;

use MM\Meros\App\Features\Feature;
use MM\Meros\App\Contracts\SettingsRegistrar;

use MM\Meros\App\Facades\Registry;

class SettingField extends Feature {

    /**
     * The slug of the menu page this field belongs to
     *
     * @var string
     */
    public string $page;

    /**
     * The ID of the settings section this field belongs to
     *
     * @var string
     */
    public string $section;

    /**
     * The title of the field
     *
     * @var string
     */
    public string $title;

    /**
     * Additional args compatible with the args parameter of add_settings_field
     *
     * @var array
     */
    public array $args;

    /**
     * The type of the field (e.g. 'text', 'checkbox', etc.)
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var string
     */
    public string $type;

    /**
     * The name attribute for the field, used in form submissions.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var string
     */
    public string $name;

    /**
     * A description for the field.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var string
     */
    public string $description;

    /**
     * The default value for the field.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var mixed
     */
    public mixed $defaultValue;

    /**
     * Whether the field is required.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var boolean
     */
    public bool $required;

    /**
     * Whether the field is disabled.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var boolean
     */
    public bool $disabled;

    /**
     * An array of options for the field, used for select fields.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var array
     */
    public array $options;

    /**
     * Additional data attributes to be passed to the field
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var array
     */
    public array $dataAttributes;

    /**
     * The callback used to render the field.
     *
     * @var Closure
     */
    public Closure $callback;


    /**
     * The AJAX action name for the field, if it supports AJAX.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var string
     */
    public string $ajaxAction;

    /**
     * The callback used to handle AJAX requests for the field.
     * Used only for reference when the field is registered by a Setting instance
     *
     * @var Closure|null
     */
    public Closure|null $ajaxCallback;

    public function __construct(
        public  SettingsRegistrar    $source,
        public  Setting|null         $setting = null,
        public  SettingsSection|null $sectionInstance = null,
    ) {
        $this->setSchema();
    }

    /**
     * Creates a SettingField instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the setting field.
     * 
     * @return self  An instance of the SettingField feature.
     */
    public function make(array $config): self {
        if ($this->setting !== null) {
            $this->handle   = $config['id'];
            $this->title    = $config['title'];
            $this->page     = $config['page'];
            $this->section  = $config['section'];
            $this->callback = $this->convertToClosure($config['callback']);
            $this->args     = $config['args'];

            $this->type           = $config['type'];
            $this->name           = $config['name'] ?? $this->setting->handle;
            $this->description    = $config['description'] ?? $this->setting->description;
            $this->defaultValue   = $config['default'] ?? $this->setting->defaultValue;
            $this->required       = $config['required'] ?? false;
            $this->disabled       = $config['disabled'] ?? false;
            $this->options        = $config['options'] ?? [];
            $this->dataAttributes = $config['data_attributes'] ?? [];

            $this->ajaxAction   = $config['ajax_action'] ?? '';
            $this->ajaxCallback = isset($config['ajax_callback']) ? $this->convertToClosure($config['ajax_callback']) : null;

            $this->ready = true;

            // We'll call the loader from the parent Setting instance so no hook here...
        }

        else {
            $sanitizedConfig = $this->sanitizeConfig($config);
            if ($sanitizedConfig !== false) {

                $this->handle         = $sanitizedConfig['id'];
                $this->title          = $sanitizedConfig['title'];
                $this->page           = $sanitizedConfig['page'];
                $this->section        = $this->sectionInstance ? $this->sectionInstance->handle : $sanitizedConfig['section'];

                $this->callback       = $this->convertToClosure($sanitizedConfig['callback']);
                $this->args           = $sanitizedConfig['args'];

                $this->ready = true;

                // Hook the load method to the admin_init action to register the settings field
                add_action('admin_init', [$this, 'load']);
            }
        }

        Registry::add('settingsFields', $this);

        return $this;
    }

    /**
     * Set the configuration schema for the setting field.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'id'       => ['type' => 'string', 'required' => true],
            'title'    => ['type' => 'string', 'required' => true],
            'callback' => ['type' => 'callable|closure', 'required' => true],
            'page'     => ['type' => 'string', 'required' => true],
            'section'  => ['type' => 'string', 'required' => false, 'default' => 'default'],
            'args'     => ['type' => 'array', 'required' => false, 'default' => []],
        ];
    }

    /**
     * Registers the setting field with WordPress.
     *
     * @return void
     */
    final public function load(): void {
        add_settings_field(
            $this->handle,
            $this->title,
            $this->callback,
            $this->page,
            $this->section,
            $this->args
        );

        $this->loaded = true;
    }
}