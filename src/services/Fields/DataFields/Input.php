<?php 

namespace MM\Meros\App\Support\Fields\DataFields;

use MM\Meros\App\Support\Fields\DataField;
use MM\Meros\App\Support\Fields\Concerns\IsInputType;

/**
 * Used for field rendering in a DataField context e.g. 
 * when the field is attached to a setting.
 * 
 * @see IsInputType for available methods and properties.
 */
class Input extends DataField {
    use IsInputType;
}