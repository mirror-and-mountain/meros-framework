<?php 

namespace MM\Meros\App\Fields;

class Email extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'email';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'email';

    /**
     * Default attributes for the email field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'email',
    ];

    /**
     * Supported features for the email field.
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