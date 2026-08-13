<?php 

namespace MM\Meros\App\FormComponents\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Choice;

class Checkboxes extends Choice {
    final protected bool $allowsMultiple = true;

    protected function configure(): void {
        $this->type('checkboxes');
        $this->dataType('array.scalar');

        // To prevent the multiple() method from being used to disable multiple selections, 
        // as checkboxes inherently allow multiple selections.
        $this->removeSupport('multiple');
    }

    protected function resolveFieldView(): string {
        return 'meros::forms.fields.checkboxes';
    }
}