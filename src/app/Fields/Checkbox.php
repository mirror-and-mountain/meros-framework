<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

class Checkbox extends Field {
    public string $handle = 'checkbox';
    public static string $icon = 'tick';

    protected array $attributes = [
        'type' => 'checkbox',
    ];

    protected array $supports = [
        'required',
        'disabled',
        'helpText'
    ];

    protected array $compatibleDataTypes = [
        'boolean'
    ];

    /**
     * Retrieves the field's current value, falling back to the default if no value is set.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        if (!is_bool($this->value)) {
            return is_bool($this->default) ? $this->default : false;
        }

        return $this->value;
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.checkbox';
    }
}