<?php 

namespace MM\Meros\App\Fields;

class Radio extends Select {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'radio';

    /**
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $category = 'choice';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'list';

    /**
     * Choice features supported by the radio field.
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * Default attributes for the radio field.
     *
     * @var array
     */
    protected array $attributes = [
        'multiple'       => false,
        'data-advanced'  => 'false',
        'data-allow-add' => 'false',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
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
        return 'meros::fields.radio';
    }
}