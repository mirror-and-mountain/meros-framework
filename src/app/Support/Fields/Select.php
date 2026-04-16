<?php 

namespace MM\Meros\App\Support\Fields;

/**
 * Used for field rendering in a non DataField context e.g. 
 * when the field is rendered on the site.
 * 
 * @see IsChoiceType for available methods and properties.
 */
class Select extends Field {
    use Concerns\IsChoiceType;

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::fields.select';
    }
}