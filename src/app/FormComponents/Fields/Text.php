<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Text extends Input {
    protected function configure(): void {
        $this->type('text');
        $this->dataType('string');
        $this->inputType('text');
    }
}