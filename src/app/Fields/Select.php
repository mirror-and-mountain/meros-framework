<?php 

namespace MM\Meros\App\Fields;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Elements\Field;

class Select extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'select';

    /**
     * The options for the select field.
     *
     * @var array
     */
    protected array $options = [];

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
        'multiple'       => false,
        'data-advanced'  => 'false',
        'data-allow-add' => 'false',
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
            foreach ($default as $value) {
                $value = (string) $value;
                $value = Str::slug($value);    

                if (!array_key_exists($value, $this->options)) {
                    $this->options[$value] = Str::title(str_replace(['-', '_'], ' ', $value));
                }

                $this->default = [];
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
                $value = Str::slug($label);
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
            $this->attribute('data-advanced', $advanced ? 'true' : 'false');
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
            $this->attribute('data-allow-add', $allowAdd ? 'true' : 'false');
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

    public function allowsAdd(): bool {
        return ($this->attributes['data-allow-add'] ?? 'false') === 'true';
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
        
        $json['options']        = $this->getOptions();
        $json['allowsMultiple'] = $this->allowsMultiple();
        $json['allowsAdd']      = $this->allowsAdd();
        $json['advanced']       = ($this->attributes['data-advanced'] ?? 'false') === 'true';
        
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
        return 'meros::fields.select';
    }
}