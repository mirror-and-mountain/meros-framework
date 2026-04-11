<?php 

namespace MM\Meros\App\Settings;

use Exception;
use Illuminate\Support\Str;

use MM\Meros\App\Contracts\FieldRegistrar;
use MM\Meros\App\Contracts\DataRegistrar;

use MM\Meros\App\Support\Feature;
use MM\Meros\App\FeatureProvider;

use MM\Meros\App\Concerns\HasFields;
use MM\Meros\App\Concerns\HasSanitizer;
use MM\Meros\App\Concerns\HasDataBuilder;

final class Setting extends Feature implements DataRegistrar, FieldRegistrar {
    // Indicates whether the setting has been registered via the register() method.
    protected bool $registered = false;

    public string $optionGroup = '';
    public array  $args = [
        'type'              => '',
        'label'             => '',
        'description'       => '',
        'default'           => null,
        'show_in_rest'      => false,
        'sanitize_callback' => null, // To be set to the default sanitizer method in the constructor
    ];

    use HasDataBuilder, HasSanitizer, HasFields;

    public function __construct(
        public FeatureProvider $source,
        string $optionGroup = '',
    ) {
        // Set the field class for this registar to use when creating fields.
        $this->fieldClass = SettingField::class;

        $this->group($optionGroup);
        $this->args['sanitize_callback'] = [$this, 'sanitizeValue'];
        
        add_action('admin_init', function() {
            $this->load($this);
        });

        $this->addToRegistry();
    }

    /**
     * Sets the setting as ready (or not) based on the setting's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->optionGroup) || empty($this->name)) {
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
            $instance->name,
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
     * Adds a sub-item to the setting. Sub-items are used for array and object settings to define the structure of the array or object.
     *
     * @param  string $path The dot-notated path for the sub-item (e.g. 'address.street' for an object setting or '*.street' for an array of objects).
     * @param  string $name The option name for the sub-item.
     * @param  string $type The type of the sub-item (e.g. 'string', 'integer', 'object', etc.).
     * @param  mixed  $default Optional default value for the sub-item.
     * @param  array  $args Optional additional arguments for the sub-item (e.g. 'label' => 'Street Address').
     * 
     * @return Setting The created sub-item as a Setting instance.
     */
    public function addSubItem(
        string $path,
        string $name,
        string $type = ''
    ): Setting {

        if (!in_array($this->args['type'], ['array', 'object'])) {
            throw new Exception("Cannot add sub-item to non-object/array setting '{$this->name}'.");
        }

        $formattedName = Str::snake($name);
        $fullPath      = trim($path, '.');

        // Prevent adding sub-items to non-object children
        if ($this->parent !== null && !in_array($this->args['type'], ['object', 'array'])) {
            throw new Exception("Cannot add sub-items to non-object child '{$this->name}'.");
        }

        $parent = $this->findParentForPath($fullPath);

        // Prevent duplicates
        foreach ($parent->subItems as $existing) {
            if ($existing->name === $formattedName) {
                throw new Exception("Duplicate property '{$formattedName}' in '{$parent->name}'");
            }
        }

        $item = app(self::class, [
            'source'      => $this->source,
            'optionGroup' => $this->optionGroup,
        ]);

        $item->name($formattedName);

        if (!empty($type)) {
            $item->type($type);
        }

        $item->parent($parent)->path($fullPath);
        $parent->subItems[] = $item;

        return $item;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Retrieves the type of the setting, which is used for field generation and validation.
     *
     * @return string|null The type of the setting (e.g. 'string', 'integer', 'array', 'object', etc.) or null if not set.
     */
    public function getType(): ?string {
        return $this->args['type'] ?? null;
    }

    /**
     * Retrieves the item type of the setting, which is used for field generation and validation of array items.
     *
     * @return string|null The item type of the setting (e.g. 'string', 'integer', 'object', etc.) or null if not set.
     */
    public function getItemType(): ?string {
        return $this->args['item_type'] ?? null;
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

    /**
     * Retrieves the current value of the setting.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        return get_option($this->name, $this->args['default'] ?? null);
    }

    /**
     * Assigns all fields to a specific admin page.
     *
     * @param  AdminPage|string $page The page instance or slug that this field belongs to.
     *
     * @return self
     */
    public function onPage(AdminPage|string $page): self {
        // Apply to root field if exists
        if ($this->field !== null) {
            $this->field->onPage($page);
        }

        // Apply recursively
        $this->walkFields(function ($field) use ($page) {
            $field->onPage($page);
        });

        return $this;
    }

    /**
     * Assign all fields to a specific section.
     *
     * @param  SettingsSection|string $section The section instance or ID that this field belongs to.
     *
     * @return self
     */
    public function inSection(SettingsSection|string $section): self {
        // Apply to root field if exists
        if ($this->field !== null) {
            $this->field->inSection($section);
        }

        // Apply recursively
        $this->walkFields(function ($field) use ($section) {
            $field->inSection($section);
        });

        return $this;
    }
}