<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Contracts\Features\Components\FieldGroup;

abstract class Choice extends Field {
    use HasOptions;
    
    final protected function init(): void {
        parent::init();
        $this->wrapper('site', '');
        $this->wrapper('settings', '');
        $this->fieldSet(true);
        $this->view('meros::forms.fields.choice');
        
        $this->setSerializableProperties(['allowsMultiple', 'options']);
        $this->ignoreProps(['options']);
        $this->addSupports(['multiple']);
    }

    protected function whenConfigured(): void {
        if (array_key_exists('options', $this->passedProps)) {
            if ($this->defaultValue === null) {
                $this->options($this->passedProps['options']);
            } elseif ($this->passedProps['options'] !== []) {
                $this->options = $this->sanitizeOptions($this->passedProps['options']);
            }
        }
        parent::whenConfigured();
    }

    /**
     * Adds builder controls specific to the choice field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData The data for the current row in the form builder.
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group->field('repeater', function (Repeater $repeater) {
            $repeater->name('options');
            $repeater->label('Options');
            $repeater->description("The field's available options");
            $repeater->addRowText('Add Option');

            $repeater->field('text', [
                'name'  => 'value',
                'label' => 'Option Value'
            ]);

            $repeater->field('text', [
                'name'  => 'label',
                'label' => 'Option Label'
            ]);
        });

        return $group;
    }
}