<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

class Checkbox extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'checkbox';

    /**
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $icon = 'tick';

    /**
     * Default attributes for the number field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'checkbox',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
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

    /***************************
     * Rendering
     ***************************/
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.checkbox';
    }
}