<?php 

namespace MM\Meros\App\Fields;

class Hidden extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'hidden';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'bars';

    /**
     * Default attributes for the hidden field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'hidden',
    ];

    /**
     * Supported attributes for the hidden field.
     *
     * @var array
     */
    protected array $supports = [];

    
    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
    ];

}