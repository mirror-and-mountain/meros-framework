<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;

class TextArea extends Field {
    protected function configure(): void {
        $this->type('textarea');
        $this->dataType('string');
        $this->view('meros::forms.fields.textarea');
    }
}