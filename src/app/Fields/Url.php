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
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'link';

    /**
     * Default attributes for the URL field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'url',
    ];

    /**
     * Supported features for the URL field.
     *
     * @var array
     */
    protected array $supports = [
        'placeholder',
        'icon'
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