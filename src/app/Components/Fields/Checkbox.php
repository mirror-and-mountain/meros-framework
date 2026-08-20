<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Checkbox extends Input {
    protected function configure(): void {
        $this->type('checkbox');
        $this->dataType('boolean');
        $this->additionalDataTypes(['string', 'integer']);
        $this->inputType('checkbox');
        $this->view('meros::forms.fields.checkbox');
    }
}