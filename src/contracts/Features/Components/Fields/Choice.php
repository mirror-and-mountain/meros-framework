<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Contracts\Features\Components\FieldGroup;

abstract class Choice extends Field {
    use HasOptions;

    public static function getBuilderControls(): ?FieldGroup {
        $group = parent::getBuilderControls();
        $group->field('repeater', function (Repeater $repeater) {
            $repeater->name('options');
            $repeater->label('Options');
            $repeater->description("The field's available options");

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
    
    final protected function init(): void {
        parent::init();
        $this->wrapper('site', '');
        $this->wrapper('settings', '');
        $this->fieldSet(true);
        $this->view('meros::forms.fields.choice');
        
        $this->setSerializableProperties(['allowsMultiple', 'options']);
        $this->addSupports(['multiple']);
    }

    protected function whenConfigured(): void {
        parent::whenConfigured();
        if (array_key_exists('options', $this->passedProps) && 
            is_array($this->passedProps['options']) &&
            !empty($this->passedProps['options'])
        ) {
            $this->options = $this->sanitizeOptions($this->passedProps['options']);
        }
    }
}