<?php 

namespace MM\Meros\App\Fields;

class Password extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'password';

    /**
     * Default attributes for the password field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'password',
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