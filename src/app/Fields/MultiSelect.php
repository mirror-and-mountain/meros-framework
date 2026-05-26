<?php 

namespace MM\Meros\App\Fields;

class MultiSelect extends Select {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'multi_select';

    /**
     * Choice features supported by the multi-select field.
     *
     * @var array
     */
    protected array $supports = [
        'allowAdd'
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'array.scalar'
    ];

    /**
     * Default attributes for the multi-select field.
     *
     * @var array
     */
    protected array $attributes = [
        'multiple'       => true,
        'data-advanced'  => 'true',
        'data-allow-add' => 'true',
    ];

    /**
     * Retrieves the field's value, ensuring it's returned as an array.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        $value = parent::getValue();
        
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        return $value;
    }
}