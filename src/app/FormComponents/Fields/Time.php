<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Time extends Input {
    protected function configure(): void {
        $this->type('time');
        $this->dataType('string');
        $this->inputType('time');
        $this->addSupport('icon');
    }
}