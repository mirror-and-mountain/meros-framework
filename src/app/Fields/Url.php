<?php 

namespace MM\Meros\App\Fields;

class Url extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'url';

    /**
     * Default attributes for the URL field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'url',
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