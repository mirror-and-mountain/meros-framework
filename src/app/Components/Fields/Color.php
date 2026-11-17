<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Color extends Input {
    protected function configure(): void {
        $this->type('color');
        $this->dataType('string');
        $this->inputType('color');
    }
}