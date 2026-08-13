<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Choice;

class Radio extends Choice {
    final protected bool $allowsMultiple = false;

    protected function configure(): void {
        $this->type('radio');
        $this->dataType('string');
        $this->removeSupport('multiple');
    }

    protected function resolveFieldView(): string {
        return 'meros::forms.fields.radio';
    }
}