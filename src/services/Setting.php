<?php 

namespace MM\Meros\Services;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Field;
use MM\Meros\Services\Contracts\Feature;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\DataRegistrant;
use MM\Meros\Services\Contracts\AdminFieldRegistrant;

class Setting extends Feature implements DataRegistrant, AdminFieldRegistrant {
    protected bool   $isProviderSetting;
    protected string $group = '';
    protected array  $args  = [
        'type'              => '',
        'label'             => '',
        'description'       => '',
        'default'           => null,
        'show_in_rest'      => false,
        'sanitize_callback' => null,
    ];

    // The setting field instance associated with this setting, if any.
    public ?SettingsField $settingsField = null;

    use Concerns\IsDataRegistrant {
        Concerns\IsDataRegistrant::field as protected makeField;
    }

    final public function __construct(
        FeatureProvider $provider,
        array           $args = []
    ) {
        parent::__construct($provider, $args);

        if ($this->args['sanitize_callback'] === null) {
            $this->args['sanitize_callback'] = [$this, 'sanitize'];
        }
        
        add_action('admin_init', function() {
            if (!$this->ready || !$this->isRoot()) {
                return;
            }
            
            $this->load();
        });
    }

    /**
     * Sets the setting as ready (or not) based on the setting's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->group) || empty($this->name)) {
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
    protected function load(): void {
        if (
            in_array($this->args['type'], ['array', 'object']) && 
            $this->args['show_in_rest'] ?? false === true
        ) {
            $this->args['show_in_rest'] = ['schema' => $this->toSchema()];
        } // If the setting is an array or object and is set to show in the REST API, convert it to a schema for registration.

        register_setting(
            $this->group,
            $this->name,
            $this->args
        );

        $this->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Returns a new Setting instance untied to the provider's root setting. 
     * Use for creating custom settings outside of the provider's root setting.
     *
     * @return Setting
     * @throws \BadMethodCallException if called on a non-provider setting.
     */
    public function make(): Setting {
        if (!$this->isProviderSetting) {
            throw new \BadMethodCallException("Cannot call make() on a non-provider setting.");
        }
        
        $newSetting = app(Setting::class, [
            'source' => $this->source
        ]);

        return $this->source->registry()->add('settings', $newSetting);
    }

    /**
     * Sets the option group for the setting.
     *
     * @param string $group The option group name.
     * 
     * @return self
     */
    public function group(string $group): self {
        $this->group = Str::snake($group);

        $this->setReady();
        return $this;
    }

    /**
     * Adds a sub-item to the setting. Sub-items are used for array and object settings to define the structure of the array or object.
     *
     * @param string $path The dot-notated path for the sub-item (e.g. 'address.street' for an object setting or '*.street' for an array of objects).
     * @param string $name The option name for the sub-item.
     * @param string $type The type of the sub-item (e.g. 'string', 'integer', 'object', etc.).
     * @param mixed  $default Optional default value for the sub-item.
     * @param array  $args Optional additional arguments for the sub-item (e.g. 'label' => 'Street Address').
     * 
     * @return Setting The created sub-item as a Setting instance.
     * @throws \InvalidArgumentException if the path is invalid or if trying to add sub-items to a non-object/array setting.
     */
    public function addSubItem(
        string $path,
        string $name,
        string $type = ''
    ): Setting {

        if (!in_array($this->args['type'], ['array', 'object'])) {
            throw new \InvalidArgumentException("Cannot add sub-item to non-object/array setting '{$this->name}'.");
        }

        $formattedName = Str::snake($name);
        $fullPath      = trim($path, '.');

        // Prevent adding sub-items to non-object children
        if ($this->parent !== null && !in_array($this->args['type'], ['object', 'array'])) {
            throw new \InvalidArgumentException("Cannot add sub-items to non-object child '{$this->name}'.");
        }

        $parent = $this->findParentForPath($fullPath);

        // Prevent duplicates
        foreach ($parent->subItems as $existing) {
            if ($existing->name === $formattedName) {
                throw new \InvalidArgumentException("Duplicate property '{$formattedName}' in '{$parent->name}'");
            }
        }

        $item = app(self::class, [
            'source'            => $this->source,
            'optionGroup'       => $this->optionGroup,
            'isProviderSetting' => $this->isProviderSetting,
        ]);

        $item->name($formattedName);

        if (!empty($type)) {
            $item->type($type);
        }

        $item->parent($parent)->path($fullPath);
        $parent->subItems[] = $item;

        return $item;
    }

    /**
     * Overrides field() method from HasFields to create a settingsField instance in addition to the field instance.
     *
     * @param string|null $type
     * @param array       $config
     * @param array       $args
     *
     * @return Field
     * @throws \InvalidArgumentException if the setting is not compatible with fields or if a field is already assigned to the setting.
     */
    public function field(?string $type = null, array $config = [], array $args = []): Field {
        $this->field = $this->makeField($type, $config, $args);

        $makeSettingField = true;

        if ($this->type === 'object') {
            $makeSettingField = false;
        }

        if ($this->parent?->args['item_type'] ?? '' === 'object') {
            $makeSettingField = false;
        }

        // Setup the setting field if needed
        if ($makeSettingField) {
            $this->settingsField = new SettingsField(
                provider: $this->provider,
                setting:  $this,
                args:     $args
            );

            $this->provider->registry()->add('settingsFields', $this->settingsField);
        }

        return $this->field;
    }

    /**
     * Assigns all fields to a specific admin page.
     *
     * @param AdminPage|string $page The page instance or slug that this field belongs to.
     *
     * @return self
     */
    public function onPage(AdminPage|string $page): self {
        if ($this->settingsField !== null) {
            $this->settingsField->onPage($page);
        }

        $this->walkSettingFields(fn ($sf) => $sf->onPage($page));

        return $this;
    }

    /**
     * Assign all fields to a specific section.
     *
     * @param SettingsSection|string $section The section instance or ID that this field belongs to.
     *
     * @return self
     */
    public function inSection(SettingsSection|string $section): self {
        if ($this->settingsField !== null) {
            $this->settingsField->inSection($section);
        }

        $this->walkSettingFields(fn ($sf) => $sf->inSection($section));

        return $this;
    }

    /***************************
     * Helpers
     ***************************/
    /** Walk through all sub-items and apply a callback to their setting fields if they exist.
     *
     * @param callable $callback A callback function that takes a SettingField instance as its parameter.
     *
     * @return void
     */
    protected function walkSettingFields(callable $callback): void {
        $this->walk(function ($item) use ($callback) {
            if ($item->settingsField) {
                $callback($item->settingsField);
            }
        });
    }

    /**
     * Retrieves the current value of the setting.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        $root = $this;

        while ($root->parent !== null) {
            $root = $root->parent;
        }

        $value = get_option($root->name, $root->args['default'] ?? null);

        // If this is the root, return directly
        if ($this === $root) {
            return $value;
        }

        // Traverse into nested structure using path
        $segments = explode('.', $this->path);

        // Remove root segment
        array_shift($segments);

        foreach ($segments as $segment) {
            if ($segment === '*') {
                // For repeaters, return full array (handled elsewhere per index)
                return $value;
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $this->args['default'] ?? null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Retrieves the default value of the setting.
     *
     * @return mixed
     */
    public function getDefault(): mixed {
        return $this->args['default'] ?? null;
    }

    /**
     * Unregisters the setting.
     *
     * @return void
     */
    public function unload(): void {
        if (!$this->isRoot()) {
            return; // Only root settings are registered.
        }

        unregister_setting($this->optionGroup, $this->name);
    }
}