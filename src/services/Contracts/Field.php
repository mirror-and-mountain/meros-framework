<?php 

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Feature;

abstract class Field extends Feature {
    /**
     * The field's unique identifier, used for HTML attributes and as a fallback for generating the name and label.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The field's name attribute, used for form submissions and as a fallback for generating the label.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The field's label, displayed to users and used as a fallback for generating the name.
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
     * The field's width option, which can be 'full', 'half', or 'third' to control its width in the form.
     *
     * @var string
     */
    protected string $width = 'full';

    /**
     * The name of the Blade component used to render the field's wrapper.
     * Defaults to 'meros::components.fields.wrappers.default', but can be overridden by specific field types if needed.
     *
     * @var string
     */
    protected string $wrapper = 'meros::components.fields.wrappers.default';

    // These properties are set to true by default as fields don't use the setReady or load methods.
    final public bool $ready = true;
    final public bool $loaded = true;

    /**
     * Renders the field using its designated view component.
     *
     * @return void
     */
    public function render(): void {
        echo view($this->wrapper, [
            'component' => $this->getFieldComponent(),
            'field'     => $this
        ]);
    }

    /***************************
     * Feature Contract Methods
     ***************************/
    final protected function load(): void {
        // Field classes don't use the load method
    }

    final protected function setReady(): void {
        // Field classes don't use the this method.
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
        $this->name = Str::replace(' ', '_', $name); // Replace spaces with underscores for valid HTML name attributes
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
     *
     * @return self
     */
    public function helpText(string $helpText): self {
        $this->helpText = $helpText;
        return $this;
    }

    /**
     * Shorthand alias for helpText() to set the field's help text.
     *
     * @param string $helpText
     *
     * @return self
     */
    public function help(string $helpText): self {
        return $this->helpText($helpText);
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

    /***************************
     * Getters
     ***************************/
    /**
     * Retrieves the field's ID.
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
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
     * Retrieves the field's current value, falling back to the default if no value is set.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        return $this->value ?? $this->default;
    }

    /**
     * Retrieves the field's name, generating it from the ID or label if not explicitly set.
     *
     * @return string
     */
    public function getName(): string {
        if (!empty($this->name)) {
            return $this->name;
        }

        if (!empty($this->id)) {
            $this->name = $this->id;
            return $this->name;
        }

        if (!empty($this->label)) {
            $this->name = Str::slug($this->label);
            return $this->name;
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
     * Retrieves the name of the Blade component responsible for rendering this field type.
     *
     * @return string
     */
    abstract public function getFieldComponent(): string;
}