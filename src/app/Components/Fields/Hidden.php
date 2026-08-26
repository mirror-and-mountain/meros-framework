<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;

class Hidden extends Input {
    protected function configure(): void {
        $this->type('hidden');
        $this->dataType('string');
        $this->inputType('hidden');
        $this->attribute('aria-hidden', 'true');
    }
}