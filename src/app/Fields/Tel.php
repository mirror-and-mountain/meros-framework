<?php 

namespace MM\Meros\App\Fields;

class Tel extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'tel';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'phone';

    /**
     * Default attributes for the tel field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'tel',
    ];

    /**
     * Supported features for the tel field.
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