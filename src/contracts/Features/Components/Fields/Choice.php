<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Features\Components\Field;

abstract class Choice extends Field {
    protected bool  $allowsMultiple = false;
    protected array $options = [];

    final protected function init(): void {
        parent::init();
        
        $this->view('meros::forms.fields.choice');
        $this->setSerializableProperties(['allowsMultiple', 'options']);
        $this->addSupports(['multiple']);
    }

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

        if (is_string($this->defaultValue) && !empty($this->defaultValue) && $allow) {
            $this->defaultValue = [$this->defaultValue];
        }

        if (is_array($this->defaultValue) && !empty($this->defaultValue) && !$allow) {
            $this->defaultValue = reset($this->defaultValue);
        }

        return $this;
    }

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
        $this->options = $options;
        return $this;
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
        if (is_array($this->defaultValue) && !$this->allowsMultiple) {
            throw new \InvalidArgumentException("Default value for field '{$this->handle}' cannot be an array when multiple selections are not allowed.");
        }

        if (is_string($this->defaultValue) && $this->allowsMultiple) {
            throw new \InvalidArgumentException("Default value for field '{$this->handle}' cannot be a string when multiple selections are allowed.");
        }

        if (is_array($this->defaultValue)) {
            foreach ($this->defaultValue as $value) {
                if (!array_key_exists($value, $this->options)) {
                    $this->options = array_merge($this->options, [$value => Str::title(str_replace('_', ' ', $value))]);
                }
            }
        } elseif (is_string($this->defaultValue) && !empty($this->defaultValue)) {
            if (!array_key_exists($this->defaultValue, $this->options)) {
                $this->options = array_merge($this->options, [$this->defaultValue => Str::title(str_replace('_', ' ', $this->defaultValue))]);
            }
        }
    }
}