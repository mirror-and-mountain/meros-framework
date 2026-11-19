<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Fields\Input;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Concerns\HasBounds;

class Number extends Input {
    use HasBounds;

    protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->configureBounds(count: false);
    }

    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group = parent::addBuilderControls($group, $locked, $rowData);
        static::addBoundsControls($group, $locked, 'Value', count: false);
        return $group;
    }

    protected function configure(): void {
        $this->type('number');
        $this->dataType('integer');
        $this->additionalDataTypes(['number']);
        $this->inputType('number');
    }
}