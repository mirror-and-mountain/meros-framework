<?php 

namespace MM\Meros\App\Support\Fields;

class Checkboxes extends Select {
    public bool $multiple = true;

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::fields.checkboxes';
    }
}