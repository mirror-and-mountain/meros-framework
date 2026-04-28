<?php 

namespace MM\Meros\App\Fields;

class Color extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'color';

    /**
     * Default attributes for the color field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'color',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
    ];
}