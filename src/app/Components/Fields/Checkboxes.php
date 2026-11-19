<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Choice;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Concerns\HasBounds;

class Checkboxes extends Choice {
    use HasBounds;

    protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->configureBounds();
    }

    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group = parent::addBuilderControls($group, $locked, $rowData);
        static::addBoundsControls($group, $locked, 'Selections');
        return $group;
    }

    final protected bool $allowsMultiple = true;

    protected function configure(): void {
        $this->type('checkboxes');
        $this->dataType('array.scalar');

        // To prevent the multiple() method from being used to disable multiple selections, 
        // as checkboxes inherently allow multiple selections.
        $this->removeSupport('multiple');
    }
}