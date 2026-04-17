<?php 

namespace MM\Meros\App\Support\Fields\DataFields;

use MM\Meros\App\Support\Fields\DataField;
use MM\Meros\App\Support\Fields\Concerns\IsChoiceType;

/**
 * Used for field rendering in a DataField context e.g. 
 * when the field is attached to a setting.
 * 
 * @see IsChoiceType for available methods and properties.
 */
class Select extends DataField {
    use IsChoiceType;

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'fields.select';
    }
}