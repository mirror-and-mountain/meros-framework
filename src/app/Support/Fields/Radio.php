<?php 

namespace MM\Meros\App\Support\Fields;

class Radio extends Select {
    public bool $multiple = false;

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::fields.radio';
    }
}