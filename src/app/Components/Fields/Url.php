<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Url extends Input {
    protected function configure(): void {
        $this->type('url');
        $this->dataType('string');
        $this->inputType('url');
        $this->addSupport('icon');
    }
}