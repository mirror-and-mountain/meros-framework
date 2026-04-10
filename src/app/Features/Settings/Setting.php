<?php 

namespace MM\Meros\App\Features\Settings;

use Closure;
use Exception;
use Illuminate\Support\Str;

use MM\Meros\App\Contracts\FieldRegistrar;
use MM\Meros\App\Contracts\ObjectRegistrar;

use MM\Meros\App\Features\Feature;
use MM\Meros\App\Features\Field;
use MM\Meros\App\FeatureProvider;

use MM\Meros\App\Features\Concerns\HasSanitizer;
use MM\Meros\App\Features\Concerns\HasObjectBuilder;

final class Setting extends Feature implements ObjectRegistrar, FieldRegistrar {
    // Used in the HasObjectBuilder trait to know what type of sub-items to create.
    protected string $featureClass = self::class;

    // Indicates whether the setting has been registered via the register() method.
    protected bool $registered = false;

    // The field instance associated with this setting if the withField() method is used.
    public ?Field $field = null;

    public string $optionGroup;
    public string $optionName;
    public array  $args = [
        'type'              => '',
        'label'             => '',
        'description'       => '',
        'default'           => null,
        'show_in_rest'      => false,
        'sanitize_callback' => null, // To be set to the default sanitizer method in the constructor
    ];

    protected array $types = ['string', 'boolean', 'integer', 'number', 'array', 'object'];

    protected const FIELD_TYPES = [
        'text',
        'email',
        'tel',
        'url',
        'password',
        'textarea',
        'checkbox',
        'number',
        'select',
        'repeater'
    ];

    use HasObjectBuilder, HasSanitizer;

    public function __construct(
        public FeatureProvider $source,
        string $type = '',
        string $optionGroup = '',
        string $optionName = ''
    ) {

        if (in_array($type, $this->types)) {
            $this->$type($optionGroup, $optionName);
        }

        else {
            $this->setGroupAndName($optionGroup, $optionName);
        }


        $this->args['sanitize_callback'] = [$this, 'sanitizeValue'];
        
        add_action('admin_init', [$this, 'register']);

        $this->setReady();
        $this->addToRegistry();
    }

    /**
     * Sets the setting as ready (or not) based on the setting's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->optionGroup) || empty($this->optionName)) {
            $this->ready = false;
            return;
        }

        $this->ready = true;
    }

    /**
     * Registers the setting with WordPress. If a field is associated with the setting,
     * it will also register the field. This method is hooked into the 'admin_init' action.
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        if (!$instance->ready || !$this->isRoot()) {
            return;
        }

        if (
            in_array($instance->args['type'], ['array', 'object']) && 
            $instance->args['show_in_rest'] ?? false === true
        ) {
            $instance->args['show_in_rest'] = ['schema' => $instance->toSchema()];
        } // If the setting is an array or object and is set to show in the REST API, convert it to a schema for registration.

        register_setting(
            $instance->optionGroup,
            $instance->optionName,
            $instance->args
        );

        // Register the field if it exists and is ready
        $field = $instance->field;

        if ($field !== null && $field->ready) {
            $field->register();
        }

        $instance->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the option group for the setting.
     *
     * @param  string $group The option group name.
     * 
     * @return self
     */
    public function group(string $group): self {
        $this->optionGroup = Str::snake($group);

        $this->setReady();
        return $this;
    }

    /**
     * Sets the option name for the setting.
     *
     * @param  string $name The option name.
     * 
     * @return self
     */
    public function name(string $name): self {
        $this->optionName = Str::snake($name);

        $this->setReady();
        return $this;
    }

    /**
     * Shorthand method to set the option name and type for a string setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function string(string $group = '', string $name = '', mixed $default = null, array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('string')->default($default);
    }

    /**
     * Shorthand method to set the option name and type for a boolean setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function boolean(string $group = '', string $name = '', mixed $default = null, array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('boolean')->default($default);
    }

    /**
     * Shorthand method to set the option name and type for an integer setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function integer(string $group = '', string $name = '', mixed $default = null, array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('integer')->default($default);
    }

    /**
     * Shorthand method to set the option name and type for a number setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function number(string $group = '', string $name = '', mixed $default = null, array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('number')->default($default);
    }

    /**
     * Shorthand method to set the option name and type for an array setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function array(string $group = '', string $name = '', mixed $default = null, array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('array')->default($default);
    }

    /**
     * Shorthand method to set the option name and type for an object setting.
     *
     * @param  string $group Optional option group name.
     * @param  string $name Optional option name.
     * @param  mixed  $default Optional default value for the setting.
     * @param  array  $args Optional additional arguments for the setting (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function object(string $group = '', string $name = '', array $args = []): self {
        $this->setGroupAndName($group, $name);
        $this->args = array_merge($this->args, $args);

        return $this->type('object')->default(null);
    }

    /**
     * Adds a sub-item to the setting. Sub-items are used for array and object settings to define the structure of the array or object.
     *
     * @param  string $path The dot-notated path for the sub-item (e.g. 'address.street' for an object setting or '*.street' for an array of objects).
     * @param  string $optionName The option name for the sub-item.
     * @param  string $type The type of the sub-item (e.g. 'string', 'integer', 'object', etc.).
     * @param  mixed  $default Optional default value for the sub-item.
     * @param  array  $args Optional additional arguments for the sub-item (e.g. 'label' => 'Street Address').
     * 
     * @return Setting The created sub-item as a Setting instance.
     */
    public function addSubItem(
        string $path,
        string $optionName,
        string $type = '',
        mixed  $default = null,
        array  $args = []
    ): Setting {

        if (!in_array($this->args['type'], ['array', 'object'])) {
            throw new Exception("Cannot add sub-item to non-object/array setting '{$this->optionName}'.");
        }

        $formattedName = Str::snake($optionName);
        $fullPath      = trim($path, '.');

        // Prevent adding sub-items to non-object children
        if ($this->parent !== null && !in_array($this->args['type'], ['object', 'array'])) {
            throw new Exception("Cannot add sub-items to non-object child '{$this->optionName}'.");
        }

        // Prevent duplicates
        foreach ($this->subItems as $item) {
            if ($item->path === $fullPath) {
                return $item;
            }
        }

        $item = app(self::class, [
            'source'      => $this->source,
            'type'        => $type,
            'optionGroup' => $this->optionGroup,
            'optionName'  => $formattedName,
        ])->args($args)->parent($this)->path($fullPath);

        if (!is_null($default)) {
            $item->default($default);
        }

        $parent = $this->findParentForPath($fullPath);
        
        $item->parent($parent);
        $parent->subItems[] = $item;

        return $item;
    }

    public function path(?string $path): self {
        $this->path = $path;
        return $this;
    }

    /**
     * Sets the type of value for the setting.
     *
     * @param  string $type The value type (e.g. 'string', 'boolean', etc.).
     * 
     * @return self
     */
    public function type(string $type): self {
        if (!in_array($type, $this->types)) {
            $this->error = "Invalid type '{$type}' specified for setting '{$this->optionName}'.";
            return $this;
        }

        $this->args['type'] = $type;

        $this->setReady();
        return $this;
    }

    /**
     * Merges the provided arguments with the existing arguments for the setting.
     *
     * @param  array $args An associative array of arguments to merge with the existing setting arguments.
     * 
     * @return self
     */
    public function args(array $args): self {
        $this->args = array_merge($this->args, $args);

        $this->setReady();
        return $this;
    }

    /**
     * Sets the label for the setting.
     *
     * @param  string $label The human-readable label for the setting.
     * 
     * @return self
     */
    public function label(string $label): self {
        $this->args['label'] = $label;

        $this->setReady();
        return $this;
    }

    /**
     * Sets the description for the setting.
     *
     * @param  string $description A description of the setting.
     * 
     * @return self
     */
    public function description(string $description): self {
        $this->args['description'] = $description;

        $this->setReady();
        return $this;
    }

    /**
     * Sets the default value for the setting.
     *
     * @param  mixed $value The default value for the setting.
     * 
     * @return self
     */
    public function default(mixed $value): self {
        $this->args['default'] = $value;

        $this->setReady();
        return $this;
    }

    /**
     * Sets whether the setting should be exposed in the REST API.
     *
     * @param  bool $show Whether to show the setting in the REST API.
     * 
     * @return self
     */
    public function showInRest(bool $show = true): self {
        $this->args['show_in_rest'] = $show;

        $this->setReady();
        return $this;
    }

    /**
     * Adds a field to the setting. The field will be used to render the setting's input in the settings page.
     *
     * @param  string $type The type of field to add (e.g. 'text', 'checkbox', etc.).
     * @param  array  $config Optional configuration for the field (e.g. 'label' => 'My Field').
     * 
     * @return SettingField The created SettingField instance.
     */
    public function withField(string $type = '', ?Closure $callback = null, array $args = []): SettingField {
        if ($this->field !== null) {
            $this->error = "Setting '{$this->optionName}' already has a field associated with it.";
            throw new \Exception($this->error);
        }

        if (!$this->compatibleWithField()) {
            $this->error = "Setting '{$this->optionName}' is not compatible with fields. Please ensure the setting has a compatible type (string, boolean, integer, or number) before adding a field.";
            throw new \Exception($this->error);
        }

        $validFieldTypes = self::FIELD_TYPES;

        if ($type === '') {
            $type = $this->getDefaultFieldType();
        }

        if (!in_array($type, $validFieldTypes)) {
            $this->error = "Invalid field type '{$type}' specified for setting '{$this->optionName}'.";
            throw new \Exception($this->error);
        }

        $field = (new SettingField(
            source:    $this->source,
            registrar: $this,
            callback:  $callback
        ))->type($type, $args);

        $this->field = $field;
        return $field;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Sets the option group and name for the setting based on the provided group and name.
     * If the option group and name are already set, this method will not override them.
     *
     * @param  string $group The option group name.
     * @param  string $name The option name.
     * 
     * @return void
     */
    protected function setGroupAndName(string $group, string $name): void {
        if (empty($this->optionGroup)) {
            $this->optionGroup = Str::snake($group);
        }

        if (empty($this->optionName)) {
            $this->optionName  = Str::snake($name);
        }
    }

    /**
     * Checks if the setting is compatible with having a field added to it.
     *
     * @return boolean
     */
    protected function compatibleWithField(): bool {
        $compatibleTypes = ['string', 'boolean', 'integer', 'number', 'array'];

        return in_array($this->args['type'] ?? '', $compatibleTypes);
    }

    /**
     * Returns a default field type using this Setting's value type.
     * To be extended for arrays and objects (repeater fields) in the future.
     *
     * @return string
     */
    public function getDefaultFieldType(): string {
        return match ($this->args['type'] ?? 'string') {
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            'array'             => 'repeater',
            default => 'text',
        };
    }

    /**
     * Unregisters the setting.
     *
     * @return void
     */
    public function unload(): void {
        unregister_setting($this->optionGroup, $this->optionName);
    }

    /**
     * Retrieves the current value of the setting.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        return get_option($this->optionName, $this->args['default'] ?? null);
    }

    /**
     * Returns the option name (handle) for the setting.
     *
     * @return string
     */
    public function getID(): string {
        return $this->optionName ?? '';
    }

    public function getName(): string {
        return $this->getID();
    }

    /**
     * Returns the settings label if available or generates a label using the provided option_name (stored in $this->optionName)
     *
     * @return string
     */
    public function getLabel(): string {
        $label = $this->args['label'] ?? '';
        
        if ($label !== '') {
            return $label;
        }

        return Str::title(Str::replace('_', ' ', $this->optionName));
    }

    /**
     * Returns the description for the setting if available.
     * Otherwise, returns an empty string.
     * 
     * @return string
     */
    public function getDescription(): string {
        return $this->args['description'] ?? '';
    }

    public function register(): void {
        $this->load($this);
    }
}