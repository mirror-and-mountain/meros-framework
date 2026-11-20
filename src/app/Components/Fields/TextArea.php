<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;

class TextArea extends Field {
    protected int $rows = 4;

    protected function configure(): void {
        $this->type('textarea');
        $this->dataType('string');
        $this->view('meros::forms.fields.textarea');
        $this->setSerializableProperties(['rows'], true);
    }

    /**
     * Sets the number of rows for the textarea field.
     *
     * @param int|null $rows
     *
     * @return static
     */
    public function rows(?int $rows): static {
        if ($rows === null || $rows === 0) {
            $rows = 4;
        }

        $this->rows = $rows;
        return $this;
    }


    /**
     * Adds builder controls specific to the text area field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData The data for the current row in the form builder.
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group->row(function ($row) use ($locked) {
            $row->field('number', [
                'name'        => 'rows',
                'label'       => 'Rows',
                'default'     => 4,
                'min'         => 1,
                'readonly'    => $locked,
                'description' => "The number of rows for the textarea field."
            ]);
        });

        return $group;
    }
}