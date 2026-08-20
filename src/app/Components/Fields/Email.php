<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Email extends Input {
    protected function configure(): void {
        $this->type('email');
        $this->dataType('string');
        $this->inputType('email');
        $this->addSupport('icon');
    }
}