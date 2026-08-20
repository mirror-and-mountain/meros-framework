<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Date extends Input {
    protected function configure(): void {
        $this->type('date');
        $this->dataType('string');
        $this->inputType('date');
        $this->addSupport('icon');
    }
}