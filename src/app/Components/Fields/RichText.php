<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;

class RichText extends Field {
    protected function configure(): void {
        $this->type('richtext');
        $this->dataType('string');
        $this->view('meros::forms.fields.richtext');
        $this->wrapper('site', '');
    }

    /**
     * Adds builder controls for the RichText field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group->field('richtext', [
            'name' => 'defaultValue',
            'label' => 'Default Value',
            'readonly' => $locked,
            'description' => "The field's default value.",
        ], false);
        return $group;
    }
}