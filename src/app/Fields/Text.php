<?php 

namespace MM\Meros\App\Fields;

class Text extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'text';

    /**
     * Default attributes for the text field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'text',
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