<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Elements\Field;

class RichText extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'rich_text';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'bars';

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
        return 'meros::fields.rich-text';
    }
}