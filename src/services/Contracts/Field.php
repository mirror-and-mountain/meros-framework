<?php 

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FieldParent;
use MM\Meros\Services\Contracts\FeatureDefinition;

abstract class Field extends FeatureDefinition {
    /**
     * The field's unique slug, should be implemented by concrete field classes 
     * to provide a unique identifier for the field type.
     *
     * @var string
     */
    public string $handle;

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [];

    /**
     * The root name for the field, used to generate names.
     *
     * @var string
     */
    protected string $rootName = '';

    /**
     * The parent field group or repeater instance if this field is a sub-field.
     *
     * @var FieldParent|null
     */
    protected ?FieldParent $parent = null;

    /**
     * The field's name, which can be used for form submission and as a fallback for generating the label.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The field's unique ID, which can be used for HTML attributes and as a fallback for generating the name.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * An associative array of additional HTML attributes to apply to the field's input element.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The field's label, displayed to users and used 
     * as a fallback for generating the name.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The field's help text, providing additional guidance to users.
     *
     * @var string
     */
    protected string $helpText = '';

    /**
     * The position of the help text, either 'top' or 'bottom'.
     *
     * @var string
     */
    protected bool $helpTextTop = false;

    /**
     * The field's default value, used when no explicit value is set.
     *
     * @var mixed
     */
    protected mixed $default = null;

    /**
     * The field's current value, which may be set explicitly or fall back to the default.
     *
     * @var mixed
     */
    protected mixed $value = null;

    /**
     * Whether the field is required for form submission.
     *
     * @var boolean
     */
    protected bool $required = false;

    /**
     * Whether the field is disabled and not interactive.
     *
     * @var boolean
     */
    protected bool $disabled = false;

    /**
     * An array of CSS classes to apply to the field's wrapper element.
     *
     * @var array
     */
    protected array $classList = [];

    /**
     * The field's width option, which can be 'full', 'half', or 'third'
     * to control its width in a form.
     *
     * @var string
     */
    protected string $width = 'full';

    /**
     * The name of the Blade component used to render the field's wrapper.
     * Defaults to 'meros::fields.wrappers.default', but can be overridden by specific field types if needed.
     *
     * @var string
     */
    protected string $wrapper = 'meros::fields.wrappers.default';

    // These properties are set to true by default as fields don't use the setReady or load methods.
    final public bool $ready = true;
    final public bool $loaded = true;

    /**
     * Renders the field using its designated view component.
     * 
     * @param bool $showLabel Whether to show the field's label in the wrapper.
     * @param bool $showHelp Whether to show the field's help text in the wrapper.
     *
     * @return void
     */
    public function render(bool $showLabel = true, bool $showHelp = true): void {
        echo view($this->wrapper, [
            'view'       => $this->getFieldComponent(),
            'field'      => $this,
            'showLabel'  => $showLabel,
            'showHelp'   => $showHelp,
        ]);
    }

    /***************************
     * Feature Contract Methods
     ***************************/
    final protected function load(): void {
        // Field classes don't use the load method
    }

    final protected function hook(): void {
        // Field classes don't use the hook method.
    }

    /***************************
     * Fluent Setters
     ***************************/
    /**
     * Sets the field's ID.
     *
     * @param string $id
     *
     * @return self
     */
    public function id(string $id): self {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the field's name attribute.
     *
     * @param string $name
     *
     * @return self
     */
    public function name(string $name): self {
        $this->name = Str::replace(' ', '_', $name);
        return $this;
    }

    /**
     * Sets the root name for the field, which is used to generate names.
     *
     * @param string $rootName The root name to set for the field.
     *
     * @return self
     */
    public function rootName(string $rootName): self {
        $this->rootName = $rootName;
        return $this;
    }

    /**
     * Sets the field's label.
     *
     * @param string $label
     *
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the field's help text.
     *
     * @param string $helpText
     * @param string $position The position of the help text, either 'top' or 'bottom'.
     *
     * @return self
     */
    public function helpText(string $helpText, string $position = 'bottom'): self {
        $this->helpText = $helpText;
        $this->helpTextPosition($position);
        return $this;
    }

    /**
     * Shorthand alias for helpText() to set the field's help text.
     *
     * @param string $helpText
     * @param string $position The position of the help text, either 'top' or 'bottom'.
     *
     * @return self
     */
    public function help(string $helpText, string $position = 'bottom'): self {
        return $this->helpText($helpText, $position);
    }

    /**
     * Sets a default value for the field.
     *
     * @param mixed $default
     *
     * @return self
     */
    public function default(mixed $default): self {
        $this->default = $default;
        return $this;
    }

    /**
     * Sets the current value of the field.
     *
     * @param mixed $value
     *
     * @return self
     */
    public function value(mixed $value): self {
        $this->value = $value;
        return $this;
    }

    /**
     * Marks the field as required if $required is true, or optional if false.
     *
     * @param boolean $required
     *
     * @return self
     */
    public function required(bool $required = true): self {
        $this->required = $required;
        return $this;
    }

    /**
     * Disables the field if $disabled is true, or enables it if false.
     *
     * @param boolean $disabled
     *
     * @return self
     */
    public function disabled(bool $disabled = true): self {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Adds one or more CSS classes to the field's class list.
     *
     * @param string|array $classes A space-separated string or an array of CSS class names.
     * 
     * @return self
     */
    public function class(string|array $classes): self {
        $classes = is_array($classes) ? $classes : explode(' ', $classes);
        $this->classList = array_merge($this->classList, $classes);
        return $this;
    }
    
    /**
     * Adds an additional HTML attribute to the field's input element.
     *
     * @param string $key The name of the HTML attribute (e.g., 'data-custom', 'aria-label').
     * @param mixed  $value The value of the HTML attribute. If null, the attribute will be rendered as a boolean attribute.
     *
     * @return self
     */
    public function attribute(string $key, mixed $value = null): self {
        if (is_null($value)) {
            $this->attributes[$key] = true; // For boolean attributes, set the value to true
            return $this;
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Sets the field's width option.
     *
     * @param string $width One of 'full', 'half', or 'third'.
     *
     * @return self
     */
    public function width(string $width): self {
        if (in_array($width, ['full', 'half', 'third'])) {
            $this->width = $width;
        }

        return $this;
    }

    /**
     * Sets the name of the Blade component used to render the field's wrapper.
     *
     * @param string $wrapper The name of the Blade component.
     *
     * @return self
     */
    public function wrapper(string $wrapper): self {
        $this->wrapper = $wrapper;
        return $this;
    }

    /**
     * Sets the position of the field's help text.
     *
     * @param string $position Either 'top' or 'bottom'.
     *
     * @return self
     */
    public function helpTextPosition(string $position): self {
        if (in_array($position, ['top', 'bottom'])) {
            $this->helpTextTop = $position === 'top';
        }

        return $this;
    }

    /**
     * Associates the field with a parent, marking it as a sub-field.
     *
     * @param FieldParent $parent The parent to associate with this field.
     *
     * @return self
     */
    public function parent(FieldParent $parent): self {
        $this->parent = $parent;
        return $this;
    }

    /***************************
     * Getters
     ***************************/
    /**
     * Returns whether the field is a sub-field of a repeater or field group.
     *
     * @return boolean
     */
    public function isSubField(): bool {
        return $this->parent !== null;
    }

    /**
     * Retrieves the field's ID.
     *
     * @return string
     */
    public function getId(): string {
        return empty($this->id) ? $this->getName() : $this->id;
    }

    /**
     * Retrieves the field's label, generating it from the name or ID if not explicitly set.
     *
     * @return string
     */
    public function getLabel(): string {
        if (!empty($this->label)) {
            return $this->label;
        }

        if (!empty($this->name)) {
            $this->label = Str::title(Str::replace(['-', '_', '[', ']'], ' ', $this->name));
            return $this->label;
        }

        if (!empty($this->id)) {
            $this->label = Str::title(Str::replace(['-', '_'], ' ', $this->id));
            return $this->label;
        }

        return '';
    }

    /**
     * Retrieves the field's help text.
     *
     * @return string
     */
    public function getHelpText(): string {
        return $this->helpText;
    }

    /**
     * Retrieves the position of the field's help text.
     *
     * @return bool
     */
    public function getHelpTextPosition(): string {
        return $this->helpTextTop ? 'top' : 'bottom';
    }

    /**
     * Retrieves the field's current value, falling back to the default if no value is set.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        $value   = is_string($this->value) ? trim($this->value) : $this->value;
        $default = is_string($this->default) ? trim($this->default) : $this->default;

        return empty($this->value) || $this->value === null 
            ? $default 
            : $value;
    }

    /**
     * Retrieves the field's name, generating it from the ID or label if not explicitly set.
     * 
     * @param bool $includeRootName Whether to include the root name for sub-fields.
     *
     * @return string
     * @throws \RuntimeException If the field cannot generate a name because it lacks a name, ID, and label.
     */
    public function getName(bool $includeRootName = true): string {
        $name = null;

        if (!empty($this->name)) {
            $name = $this->name;
        }

        if ($name === null && !empty($this->id)) {
            $this->name = $this->id;
            $name = $this->name;
        }

        if ($name === null && !empty($this->label)) {
            $this->name = Str::slug($this->label);
            $name = $this->name;
        }

        if ($name !== null) {
            if ($this->isSubField() && !$includeRootName) {
                return $name;
            }

            if ($this->isSubField() && $includeRootName) {
                return Str::replace(['[', ']'], '', Str::afterLast($name, '['));
            }

            if ($includeRootName && $this->rootName !== '') {
                return "{$this->rootName}[{$name}]";
            }

            return $name;
        }

        throw new \RuntimeException('Field must have a name, id, or label to generate a name.');
    }

    /**
     * Checks if the field is marked as required.
     *
     * @return boolean
     */
    public function isRequired(): bool {
        return $this->required;
    }

    /**
     * Checks if the field is marked as disabled.
     *
     * @return boolean
     */
    public function isDisabled(): bool {
        return $this->disabled;
    }

    /**
     * Retrieves the field's CSS class list as a space-separated string.
     *
     * @return string
     */
    public function classList(): string {
        return implode(' ', $this->classList);
    }

    /**
     * Retrieves the field's HTML attributes as a string of key="value" pairs.
     * 
     * @param array $exclude An array of attribute keys to exclude from the output (e.g., ['id', 'name']).
     *
     * @return string
     */
    public function attributes(array $exclude = []): string {
        $attributes = array_merge([
            'id'       => $this->getId(),
            'name'     => $this->getName(!$this->isSubField()),
            'class'    => $this->classList(),
            'disabled' => $this->disabled,
            'required' => $this->required,
        ], $this->attributes);

        $rendered = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            if (is_null($value) || $value === '' || $value === false) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $rendered[] = $key;
                }
                continue;
            }

            $rendered[] = sprintf(
                '%s="%s"',
                $key,
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }

        return implode(' ', $rendered);
    }

    /**
     * Retrieves the field's width option.
     *  
     * @return string
     */
    public function getWidth(): string {
        return $this->width;
    }

    /**
     * Retrieves the name of the Blade component used to render the field's wrapper.
     *
     * @return string
     */
    public function getWrapper(): string {
        return $this->wrapper;
    }

    /**
     * Retrieves the field's variation, which is determined by the 'type' attribute if it exists.
     *
     * @return string|null
     */
    public function getVariation(): ?string {
        if (array_key_exists('type', $this->attributes)) {
            return $this->attributes['type'];
        }

        return null;
    }

    /**
     * Checks if the field is compatible with a given data type.
     *
     * @param string $dataType The data type to check compatibility with (e.g., 'string', 'integer', 'array').
     *
     * @return boolean True if the field is compatible with the data type, false otherwise.
     */
    public function isCompatibleWith(string $dataType): bool {
        return in_array($dataType, $this->compatibleDataTypes);
    }

    /**
     * Retrieves the name of the Blade component responsible for rendering this field type.
     *
     * @return string
     */
    abstract public function getFieldComponent(): string;
}