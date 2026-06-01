<?php 

namespace MM\Meros\Services\Contracts\Admin;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;
use MM\Meros\Services\Contracts\Interfaces\AdminFieldRegistrant;

use MM\Meros\Services\Concerns\IsDataRegistrant;

class Setting extends FeatureDefinition implements DataRegistrant, AdminFieldRegistrant {
    
    /**
     * The option group that this setting belongs to.
     *
     * @var string
     */
    protected string $group = '';

    /**
     * The settings field instance associated with this setting, if any.
     *
     * @var SettingsField|null
     */
    protected ?SettingsField $settingsField = null;

    use IsDataRegistrant {
        IsDataRegistrant::field as protected makeField;
    }

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setDefaultArgs();
        $this->setProps($props);

        if ($this->args['sanitize_callback'] === null) {
            $this->args['sanitize_callback'] = [$this, 'sanitize'];
        }

        if (is_string($this->field) && !empty($this->field)) {
            $this->field($this->field);
            $this->makeSettingsField();
        }

        if ($this->canBeParent()) {
            $this->instantiateSubItems();
        }

        $this->queue();
    }

    /**
     * Sets default arguments for the setting.
     *
     * @return void
     */
    final protected function setDefaultArgs(): void {
        $this->args = array_merge($this->args, [
            'type'              => '',
            'label'             => '',
            'description'       => '',
            'default'           => null,
            'show_in_rest'      => false,
            'sanitize_callback' => null,
        ]);
    }

    /**
     * Queues the setting to be loaded via a WordPress hook if all the required properties are set.
     *
     * @return void
     */
    final protected function queue(): void {
        if (empty($this->group) || empty($this->name)) {
            return;
        }

        if ($this->name === 'placeholder_name') {
            return;
        }

        if ($this->isRoot() && !$this->queued) {
            add_action('admin_init', function() {
                $this->register();
            });
        }

        $this->queued = true;
    }

    /**
     * Registers the setting with WordPress. If a field is associated with the setting,
     * it will also register the field. This method is hooked into the 'admin_init' action.
     *
     * @return void
     */
    final protected function register(): void {
        if (
            in_array($this->type, ['array', 'object']) && 
            $this->args['show_in_rest'] ?? false === true
        ) {
            $this->args['show_in_rest'] = ['schema' => $this->toSchema()];
        } // If the setting is an array or object and is set to show in the REST API, convert it to a schema for registration.

        register_setting(
            $this->group,
            $this->name,
            $this->args
        );
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the option group for the setting.
     *
     * @param string $group The option group name.
     * 
     * @return self
     */
    final public function group(string $group): self {
        $this->group = Str::snake($group);

        // Update group for all sub-items if this is the root setting
        if ($this->isRoot() && !empty($this->subItems)) {
            foreach ($this->subItems as $item) {
                $item->group($group);
            }
        }

        $this->queue();
        return $this;
    }

    /**
     * Assigns the setting's field to a specific settings section.
     *
     * @param SettingsSection|string $section The section instance or a fully-qualified class name or ID.
     *
     * @return self
     */
    final public function section(SettingsSection|string $section): self {
        if ($this->settingsField !== null) {
            $this->settingsField->section($section);
        }

        return $this;
    }

    /***************************
     * Trait Overrides
     ***************************/

    /**
     * Overrides field() method from IsAdminFieldRegistrant to create a settingsField instance in addition to the field instance.
     *
     * @param  Field|string|null $type    The type of field to add (e.g. 'text', 'checkbox', etc.), a Field instance, a Field class name, or null to infer the field type.
     * @param  array             $props   Optional properties for the field.
     * @param  array             $args    Additional arguments for the field. Not used by default, but may be used in child overrides of this method.
     *
     * @return Field
     * @throws \BadMethodCallException if the setting is not compatible with fields.
     */
    final public function field(Field|string|null $type = null, array $props = [], array $args = []): Field {
        $this->makeField($type, $props, $args);
        $this->makeSettingsField($args);

        return $this->field;
    }

    /**
     * Overrides attach() method from IsDataRegistrant to ensure group is passed to any sub-items created.
     *
     * @param string $itemClass
     * @param array  $props
     *
     * @return Setting
     */
    final protected function makeSubItem(string $itemClass, array $props = []): Setting {
        return app($itemClass, [
            'provider' => $this->provider,
            'props'    => array_merge([
                'group' => $this->group
            ], $props)
        ]);
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Creates a settings field instance for this setting if applicable. This is used when a field is assigned to the setting, either via the field() method or by attaching an existing field instance.
     *
     * @param array $args Optional arguments to pass to the SettingsField constructor.
     *
     * @return void
     */
    final protected function makeSettingsField(array $args = []): void {
        $makeSettingField = true;

        if ($this->type === 'object') {
            $makeSettingField = false;
        }

        if ($this->parent?->getItemDataType() === 'object') {
            $makeSettingField = false;
        }

        if (!$makeSettingField) {
            return;
        }

        $this->settingsField = new SettingsField(
            provider: $this->provider,
            setting:  $this,
            args:     $args
        );

        $this->field->settingsField($this->settingsField);
    }

    /** Walk through all sub-items and apply a callback to their setting fields if they exist.
     *
     * @param callable $callback A callback function that takes a SettingField instance as its parameter.
     *
     * @return void
     */
    final protected function walkSettingFields(callable $callback): void {
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
    final public function getValue(): mixed {
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
    final public function getDefault(): mixed {
        return $this->args['default'] ?? null;
    }

    /**
     * Gets the slug of the admin page that this setting's field belongs to, if a field is associated with the setting.
     *
     * @return string|null
     */
    final public function getPage(): ?string {
        if ($this->settingsField !== null) {
            return $this->settingsField->getPageSlug();
        }

        return null;
    }

    /**
     * Unregisters the setting.
     *
     * @return void
     */
    final public function unload(): void {
        if (!$this->isRoot()) {
            return; // Only root settings are registered.
        }

        unregister_setting($this->group, $this->name);
    }
}