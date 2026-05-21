<?php 

namespace MM\Meros\App\Fields;

class Checkboxes extends Select {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'checkboxes';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'tick';

    /**
     * Choice features supported by the checkboxes field.
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * Default attributes for the checkboxes field.
     *
     * @var array
     */
    protected array $attributes = [
        'multiple'       => true,
        'data-advanced'  => 'false',
        'data-allow-add' => 'false',
        'type'           => 'checkbox',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'array.scalar'
    ];


    /***************************
     * Rendering
     ***************************/
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::fields.choice';
    }
}