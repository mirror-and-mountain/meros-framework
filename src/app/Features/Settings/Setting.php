<?php 

namespace MM\Meros\App\Features\Settings;

use Closure;

use Illuminate\Support\Str;

use MM\Meros\App\Features\Feature;
use MM\Meros\App\Contracts\SettingsRegistrar;

use MM\Meros\App\Facades\Registry;
use MM\Meros\App\Support\Admin\Field;

class Setting extends Feature {
    /**
     * The option group this setting belongs to.
     *
     * @var string
     */
    public string $optionGroup;

    /**
     * The type of value for the setting e.g. 'text,'boolean' etc.
     *
     * @var string
     */
    public string $type;

    /**
     * The human-readable label for the setting.
     *
     * @var string
     */
    public string $label;

    /**
     * A description of the setting.
     *
     * @var string
     */
    public string $description;

    /**
     * The default value for the setting.
     *
     * @var mixed
     */
    public mixed $defaultValue;

    /**
     * Whether to show this setting in the REST API. Accepts a boolean or an array for REST Schema.
     *
     * @var bool|array
     */
    public bool|array $showInRest;

    /**
     * A callable or method reference for sanitizing the setting's value.
     *
     * @var Closure
     */
    public Closure $sanitizeCallback;

    /**
     * Configuration for a settings field to be generated for this setting.
     * Can be set to false to not generate a field.
     * 
     * @var array|false
     */
    public array|false $fieldConfig;

    /**
     * The settings field associated with this setting, if any.
     * 
     * @var SettingField
     */
    public SettingField $field;

    public function __construct(
        public SettingsRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates a Setting instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the setting.
     * 
     * @return self  An instance of the Setting feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->handle      = $sanitizedConfig['option_name'];
            $this->optionGroup = $sanitizedConfig['option_group'];

            $this->type         = $sanitizedConfig['type'];
            $this->label        = $sanitizedConfig['label'];
            $this->description  = $sanitizedConfig['description'];
            $this->defaultValue = $sanitizedConfig['default'];

            $this->showInRest        = $sanitizedConfig['show_in_rest'];
            $this->sanitizeCallback  = $this->convertToClosure($sanitizedConfig['sanitize_callback']);

            $this->ready = true;

            // Hook the load method to the admin_init action to register the setting
            add_action('admin_init', [$this, 'load']);
        }

        Registry::add('settings', $this);

        return $this;
    }

    /**
     * Chainable method to create a SettingField instance associated with this setting.
     * 
     * @param  array $fieldConfig Configuration array for the field
     * @param  bool  $returnField Whether to return the field instance instead of the setting instance
     * 
     * @return self|SettingField
     */
    public function withField(array $fieldConfig, bool $returnField = false): self|SettingField {
        // Check the field hasn't already been made
        if (isset($this->field)) {
            $this->error = "A field has already been associated with the setting '{$this->handle}'.";
            return $this->field; // Field already exists, so return the instance with error set
        }
       
        // Sanitize the field config
        $fieldConfig = $this->sanitizeFieldConfig($fieldConfig);

        if ($fieldConfig === false) {
            return $this; // Invalid field config, so just return the setting instance with error set
        }

        // Set the default callback if set
        if ($fieldConfig['callback'] === 'default') {
            $fieldConfig['callback'] = function() use ($fieldConfig) {
               echo Field::make(
                    name: $fieldConfig['name'],
                    type: $fieldConfig['type'],
                    value: $this->getValue(),
                    id: $fieldConfig['id'],
                    required: $fieldConfig['required'],
                    disabled: $fieldConfig['disabled'],
                    options: $fieldConfig['options'],
                    ajaxAction: $fieldConfig['ajax_action'],
                    attributes: $fieldConfig['data_attributes'],
                    nonce: $fieldConfig['nonce']
                );
            };
        }

        // Get the section instance to associate with the field, if it exists
        $section = Registry::get('settingsSections')->where('handle', $fieldConfig['section'])->first();

        $this->fieldConfig = $fieldConfig;

        // Create the associated field instance
        $this->field = app(SettingField::class, [
            'source'           => $this->source,
            'setting'          => $this,
            'sectionInstance'  => $section
        ])->make($fieldConfig);

        return $returnField ? $this->field : $this;
    }

    /**
     * Set the configuration schema for the setting.
     *
     * @return void
     */
    protected function setSchema(): void {
        $allowedTypes = [
            'string',
            'boolean',
            'integer',
            'number',
            'array',
            'object'
        ];

        $this->configSchema = [
            'option_name'       => ['type' => 'string', 'required' => true],
            'option_group'      => ['type' => 'string', 'required' => true],
            'type'              => ['type' => 'string', 'required' => true, 'allowed_values' => $allowedTypes],
            'label'             => ['type' => 'string|null', 'required' => false, 'default' => null],
            'description'       => ['type' => 'string|null', 'required' => false, 'default' => null],
            'default'           => ['type' => 'mixed|null', 'required'  => false, 'default' => null],
            'show_in_rest'      => ['type' => 'boolean|array', 'required' => false, 'default' => false],
            'sanitize_callback' => ['type' => 'callable|closure', 'required' => false, 'default' => [$this, 'sanitizeValue']],
        ];
    }

    /**
     * Sanitizes/generates field config if provided via the withField() method
     *
     * @param  array       $fieldConfig
     *
     * @return array|false Sanitized field config array if valid, or false if invalid with error message set.
     */
    private function sanitizeFieldConfig(array $fieldConfig): array|false {
        $page     = $fieldConfig['page'] ?? null;
        $callback = $fieldConfig['callback'] ?? null;

        if ($page === null || $callback === null) {
            $this->error = "Field configuration for setting '{$this->handle}' is missing the required 'page' or 'callback' parameter.";
            return false;
        }

        // Set up additional config for use with the Field::make() method if using the default callback
        if ($callback === 'default') {
            $allowedFieldTypes = [
                'text',
                'url',
                'email',
                'tel',
                'password',
                'date',
                'textarea',
                'number',
                'checkbox',
                'select',
                'multi_select',
                'color',
                'custom_html'
            ];

            $defaultFieldType = $this->getDefaultFieldType($this->type);

            $schema = [
                'page'           => ['type' => 'string', 'required' => true],
                'callback'       => ['type' => 'string', 'required' => true],
                'id'             => ['type' => 'string', 'required' => false, 'default' => Str::snake($this->handle) . '_field'],
                'title'          => ['type' => 'string', 'required' => false, 'default' => $this->label],
                'section'        => ['type' => 'string', 'required' => false, 'default' => 'default'],
                'args'           => ['type' => 'array',  'required' => false, 'default' => []],

                'type'            => ['type' => 'string',  'required' => false, 'allowed_values' => $allowedFieldTypes, 'default' => $defaultFieldType],
                'name'            => ['type' => 'string',  'required' => false, 'default' => $this->handle],
                'description'     => ['type' => 'string',  'required' => false, 'default' => $this->description],
                'default'         => ['type' => 'mixed',   'required' => false, 'default' => $this->defaultValue],
                'required'        => ['type' => 'boolean', 'required' => false, 'default' => false],
                'disabled'        => ['type' => 'boolean', 'required' => false, 'default' => false],
                'options'         => ['type' => 'array',   'required' => false, 'default' => []],
                'data_attributes' => ['type' => 'array',   'required' => false, 'default' => []],
                'nonce'           => ['type' => 'string',  'required' => false, 'default' => ''],

                'ajax_action'    => ['type' => 'string', 'required' => false, 'default' => ''],
                'ajax_callback'  => ['type' => 'callable|closure|null', 'required' => false, 'default' => null],
            ];

            $fieldConfig = $this->sanitizeConfig($fieldConfig, $schema);
        }

        else {
            $schema = [
                'page'           => ['type' => 'string', 'required' => true],
                'callback'       => ['type' => 'callable|closure', 'required'  => true],
                'id'             => ['type' => 'string', 'required' => false, 'default' => Str::snake($this->handle) . '_field'],
                'title'          => ['type' => 'string', 'required' => false, 'default' => $this->label],
                'section'        => ['type' => 'string', 'required' => false, 'default' => 'default'],
                'args'           => ['type' => 'array',  'required' => false, 'default' => []]
            ];

            $fieldConfig = $this->sanitizeConfig($fieldConfig, $schema);
        }


        return $fieldConfig;
    }

    /**
     * Returns a default field type using this Setting's value type.
     * To be extended for arrays and objects (repeater fields) in the future.
     *
     * @param  string $settingType
     *
     * @return string
     */
    private function getDefaultFieldType(string $settingType): string {
        return match ($settingType) {
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            default => 'text',
        };
    }

    /**
     * Default sanitizer for settings values.
     *
     * @param  mixed $value
     *
     * @return mixed
     */
    final public function sanitizeValue(mixed $value): mixed {
        if (isset($this->field)) {
            $requiredType = $this->field->type;
        } else {
            $requiredType = $this->type;
        }

        $type = gettype($value);

        switch ($requiredType) {
            case 'string':
            case 'text':
            case 'tel':
            case 'password':
            case 'date':
            case 'textarea':
            case 'select':
                $value = $this->sanitizeTextValue($value, $type, $requiredType);
                break;

            case 'color':
                $value = sanitize_hex_color($value);
                break;

            case 'url':
                $value = sanitize_url($value);
                break;

            case 'email':
                $value = sanitize_email($value);
                break;

            case 'integer':
                $value = (int) $value;
                break;

            case 'number':
                $value = (float) $value;
                break;

            case 'boolean':
            case 'checkbox':
                $value = (bool) $value;
                break;
        }

        return $value;
    }

    /**
     * Helper to sanitize text values. Called by the sanitizeValue method.
     *
     * @param mixed  $value
     * @param string $type
     * @param string $requiredType
     * @return string
     */
    private function sanitizeTextValue(mixed $value, string $type, string $requiredType): string {
        if ($type === 'string') {
            if (in_array($requiredType, ['text', 'select'])) {
                $value = sanitize_text_field($value);
            } elseif ($requiredType === 'textarea') {
                $value = sanitize_textarea_field($value);
            }
        } elseif (in_array($type, ['integer', 'boolean', 'double'])) {
            $value = (string) $value;
        }

        return $value;
    }

    /**
     * Registers the setting with WordPress.
     *
     * @return void
     */
    final public function load(): void {
        register_setting(
            $this->optionGroup, // Used for option_group
            $this->handle,
            [
                'type'              => $this->type,
                'label'             => $this->label,
                'description'       => $this->description,
                'sanitize_callback' => $this->sanitizeCallback,
                'show_in_rest'      => $this->showInRest,
                'default'           => $this->defaultValue
            ]
        );

        // Load the associated field if it exists
        if (isset($this->field)) {
            $this->field->load();
        }

        $this->loaded = true;
    }

    /**
     * Unregisters the setting.
     *
     * @return void
     */
    final public function unload(): void {
        unregister_setting($this->optionGroup, $this->handle);
    }

    /**
     * Retrieves the current value of the setting.
     *
     * @return mixed
     */
    final public function getValue(): mixed {
        return get_option($this->handle, $this->defaultValue);
    }
}