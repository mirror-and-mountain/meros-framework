<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Password extends Input {
    protected function configure(): void {
        $this->type('password');
        $this->dataType('string');
        $this->inputType('password');
        $this->addSupport('icon');
    }
}