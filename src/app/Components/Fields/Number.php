<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Number extends Input {
    protected function configure(): void {
        $this->type('number');
        $this->dataType('integer');
        $this->additionalDataTypes(['number']);
        $this->inputType('number');
    }
}