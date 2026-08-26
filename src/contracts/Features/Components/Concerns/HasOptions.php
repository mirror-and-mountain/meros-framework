<?php

namespace MM\Meros\Contracts\Features\Components\Concerns;

use Illuminate\Support\Str;

trait HasOptions {
    protected bool  $allowsMultiple = false;
    protected array $options = [];

    abstract public function getDefaultValue(): mixed;
    abstract public function getType(): string;
    abstract public function supports(string $feature): bool;
    abstract protected function dataType(string $type): void;

    /**
     * Sets the field to allow multiple selections, if supported.
     * 
     * If a default value is already set, this method will ensure that the default value is 
     * compatible with the allowsMultiple setting. 
     * 
     * If the default value is a string and multiple selections are allowed, 
     * it will be converted to an array. If the default value is an array and multiple 
     * selections are not allowed, the first value of the default value array will be used as
     * the default value.
     *
     * @param bool $allow Defaults to true. Set to false to disallow multiple selections.
     *
     * @return static
     */
    public function multiple(bool $allow = true): static {
        if (!$this->supports('multiple')) {
            return $this;
        }

        $this->allowsMultiple = $allow;

        $defaultValue = $this->getDefaultValue();

        if (is_string($defaultValue) && !empty($defaultValue) && $allow) {
            $defaultValue = [$defaultValue];
        }

        if (is_array($defaultValue) && !empty($defaultValue) && !$allow) {
            $defaultValue = reset($defaultValue);
        }

        if ($allow) {
            $this->dataType('array.scalar');
        } else {
            $this->dataType('string');
        }

        $this->whenMultipleSet($allow);
        return $this;
    }

    /**
     * May be overridden to handle any actions after the field is set to allow multiple values.
     *
     * @param boolean $allow
     *
     * @return void
     */
    protected function whenMultipleSet(bool $allow): void {} 

    /**
     * Sets the options for the choice field. 
     * 
     * Options should be provided as an associative array where the keys are 
     * the option values and the values are the option labels. Example:
     * 
     * ['value1' => 'Label 1', 'value2' => 'Label 2']
     *
     * @param array $options
     *
     * @return static
     */
    public function options(array $options): static {
        $this->options = $this->sanitizeOptions($options);
        return $this;
    }

    /**
     * Sanitizes the given options array, ensuring each option is keyed by a string value and has a string label.
     *
     * @param array $options
     *
     * @return array
     */
    private function sanitizeOptions(array $options): array {
        $checkedOptions = [];
        foreach ($options as $value => $label) {
            if (!is_string($label) || empty($label)) {
                continue;
            }

            if (is_int($value)) {
                $value = Str::title(Str::replace(['-', '_'], ' ', $value));
                $checkedOptions[$value] = $label;
            }

            $checkedOptions[$value] = $label;
        }

        return $checkedOptions;
    }

    /**
     * Called after the default value is set to perform additional validation or processing.
     * 
     * Here we check that the default value is compatible with the allowsMultiple setting and 
     * ensure that any default values are present in the options array.
     *
     * @return void
     * @throws \InvalidArgumentException if the default value is not compatible with the allowsMultiple setting.
     */
    protected function whenDefaultValueSet(): void {
        $defaultValue = $this->getDefaultValue();
        $type = $this->getType();

        if (is_array($defaultValue) && !$this->allowsMultiple) {
            throw new \InvalidArgumentException("Default value for field '{$type}' cannot be an array when multiple selections are not allowed.");
        }

        if (is_string($defaultValue) && $this->allowsMultiple) {
            throw new \InvalidArgumentException("Default value for field '{$type}' cannot be a string when multiple selections are allowed.");
        }

        if (is_array($defaultValue)) {
            foreach ($defaultValue as $value) {
                if (!array_key_exists($value, $this->options)) {
                    $this->options = array_merge($this->options, [$value => Str::title(str_replace('_', ' ', $value))]);
                }
            }
        } elseif (is_string($defaultValue) && !empty($defaultValue)) {
            if (!array_key_exists($defaultValue, $this->options)) {
                $this->options = array_merge($this->options, [$defaultValue => Str::title(str_replace('_', ' ', $defaultValue))]);
            }
        }
    }

    /**
     * Returns whether the field allows multiple values.
     *
     * @return boolean
     */
    public function allowsMultiple(): bool {
        return $this->allowsMultiple;
    }
}