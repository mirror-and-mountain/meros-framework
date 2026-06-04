<?php 

namespace MM\Meros\App\Fields;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Forms\Field;

class Select extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'select';

    /**
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $category = 'choice';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'list';

    /**
     * The options for the select field.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Indicates whether to use an advanced UI for the choice field (e.g., tomselect).
     *
     * @var bool|null
     */
    protected ?bool $advanced = null;

    /**
     * Indicates whether to allow adding new options in the UI.
     * Applies to advanced select fields only.
     *
     * @var bool|null
     */
    protected ?bool $allowAdd = null;

    /**
     * Features supported by the choice-type field.
     * Can be overridden by child classes.
     *
     * @var array
     */
    protected array $supports = [
        'multiple',
        'advanced',
        'allowAdd',
    ];

    /**
     * Default attributes for the select field.
     *
     * @var array
     */
    protected array $attributes = [
        'multiple' => false,
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
    ];


    /**
     * Checks if the field supports a given feature.
     *
     * @param string $feature The feature to check (e.g., 'multiple', 'advanced').
     *
     * @return bool True if the feature is supported, false otherwise.
     */
    protected function supports(string $feature): bool {
        return in_array($feature, $this->supports);
    }

    /***************************
    * Fluent Setters
    ***************************/

    /**
     * Sets a default value for the field.
     *
     * @param mixed $default
     *
     * @return self
     */
    public function default(mixed $default): self {
        if (is_array($default)) {
            $this->default = [];
            foreach ($default as $value) {
                $value = (string) $value;
                $value = Str::snake($value);    

                if (!array_key_exists($value, $this->options)) {
                    $this->options[$value] = Str::title(str_replace(['-', '_'], ' ', $value));
                }

                $this->default[] = $value;
            }

            return $this;
        }

        else if (!array_key_exists($default, $this->options)) {
            $this->options[$default] = Str::title(str_replace(['-', '_'], ' ', $default));
        }

        $this->default = $default;
        return $this;
    }

    /**
     * Sets the options for the select field.
     *
     * @param array $options An associative array of options (value => label).
     *
     * @return self
     */
    public function options(array $options): self {
        foreach ($options as $value => $label) {
            if (is_int($value)) {
                $value = Str::snake($label);
            }

            if (!array_key_exists($value, $this->options)) {
                $this->options[$value] = $label;
            }
        }

        return $this;
    }

    /**
     * Sets whether the field allows multiple selections.
     *
     * @param boolean $multiple
     *
     * @return self
     */
    public function multiple(bool $multiple = true): self {
        if ($this->supports('multiple')) {
            $this->attribute('multiple', $multiple);
        }
        return $this;
    }

    /**
     * Sets whether to use an advanced UI for the choice field (e.g., tomselect).
     *
     * @param boolean $advanced
     *
     * @return self
     */
    public function advanced(bool $advanced = true): self {
        if ($this->supports('advanced')) {
            $this->advanced = $advanced;
        }
        return $this;
    }

    /**
     * Sets whether to allow adding new options in the UI.
     * Applies only if $advanced is true.
     *
     * @param boolean $allowAdd
     *
     * @return self
     */
    public function allowAdd(bool $allowAdd = true): self {
        if ($this->supports('allowAdd')) {
            $this->allowAdd = $allowAdd;
        }
        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the options for the choice field.
     *
     * @return array The options as an associative array (value => label).
     */
    public function getOptions(): array {
        $value = $this->getValue();
        
        if ($this->allowsAdd() && is_array($value)) {
            foreach ($value as $item) {
                if (!array_key_exists($item, $this->options)) {
                    $this->options[$item] = Str::title(str_replace(['-', '_'], ' ', $item));
                }
            }
        }

        if ($this->allowsAdd() && is_string($value) && !array_key_exists($value, $this->options)) {
            $this->options[$value] = Str::title(str_replace(['-', '_'], ' ', $value));
        }

        return $this->options;
    }

    /**
     * Checks if the field allows multiple selections.
     *
     * @return bool True if multiple selections are allowed, false otherwise.
     */
    public function allowsMultiple(): bool {
        return $this->attributes['multiple'] ?? false;
    }

    /**
     * Checks if the field allows adding new options in the UI.
     *
     * @return bool True if adding new options is allowed, false otherwise.
     */
    public function allowsAdd(): bool {
        if ($this->supports('allowAdd') && $this->isAdvanced()) {
            return $this->allowAdd ?? false;
        }

        return false;
    }

    /**
     * Checks if the field is set to use an advanced UI (e.g., tomselect).
     *
     * @return bool True if advanced UI is enabled, false otherwise.
     */
    public function isAdvanced(): bool {
        if ($this->supports('advanced')) {
            return $this->advanced ?? false;
        }

        return false;
    }

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson();
        
        $json['properties']['options']        = $this->getOptions();
        $json['properties']['allowsMultiple'] = $this->allowsMultiple();
        $json['properties']['allowAdd']       = $this->allowsAdd();
        $json['properties']['advanced']       = $this->isAdvanced();
        
        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    /***************************
     * Rendering
     ***************************/
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.select';
    }
}