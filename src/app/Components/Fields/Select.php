<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Contracts\Features\Components\FieldGroup;

class Select extends Field {
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

        $group->field('checkbox', [
            'name'  => 'allowsMultiple',
            'label' => 'Allow Multiple Values'
        ]);

        return $group;
    }
    
    protected function init(): void {
        parent::init();
        $this->dataType('string');
        $this->additionalDataTypes(['array.scalar']);
        $this->view('meros::forms.fields.select');
        
        $this->setSerializableProperties(['allowsMultiple', 'options']);
        $this->addSupports(['multiple']);
    }

    /**
     * Makes the select field searchable using TomSelect.
     *
     * @param boolean $searchable
     *
     * @return static
     */
    public function searchable(bool $searchable = true): static {
        if ($searchable) {
            $this->attribute('ts-searchable', true);
        } else {
            $this->removeAttribute('ts-searchable');
        }

        return $this;
    }

    protected function whenMultipleSet(bool $allow): void {
        if ($allow) {
            $this->attribute('multiple', true);
        } else {
            $this->removeAttribute('multiple');
        }
    }

    protected function whenConfigured(): void {
        parent::whenConfigured();
        if (array_key_exists('options', $this->passedProps) && 
            is_array($this->passedProps['options']) &&
            !empty($this->passedProps['options'])
        ) {
            $this->options = $this->sanitizeOptions($this->passedProps['options']);
        }

        if (array_key_exists('allowsMultiple', $this->passedProps)) {
            $this->multiple((bool) $this->passedProps['allowsMultiple']);
        }
    }
}