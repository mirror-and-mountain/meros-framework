<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Tel extends Input {
    protected function configure(): void {
        $this->type('tel');
        $this->dataType('string');
        $this->inputType('tel');
        $this->addSupport('icon');
    }
}