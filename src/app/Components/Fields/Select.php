<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Repeater;

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

        $group->field('checkbox', function (Checkbox $field) {
            $field->name('searchable');
            $field->label('Searchable');
            $field->setValueWhen([
                ['allowsMultiple', '=', true, true]
            ]);
            $field->disableWhen([
                ['allowsMultiple', '=', true]
            ]);
        });

        return $group;
    }
    
    protected function init(): void {
        parent::init();
        $this->dataType('string');
        $this->additionalDataTypes(['array.scalar']);
        $this->view('meros::forms.fields.select');
        
        $this->setSerializableProperties(['allowsMultiple', 'options', 'searchable']);
        $this->addSupports(['multiple']);
    }

    public function getDataType(): string {
        return $this->allowsMultiple() 
            ? 'array.scalar'
            : 'string';
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

    /**
     * Sets a searchable select field to allow users to add custom values.
     *
     * @param boolean $allowAdd
     *
     * @return static
     */
    public function allowAdd(bool $allowAdd = true): static {
        if ($allowAdd) {
            $this->attribute('ts-allow-add', true);
        } else {
            $this->removeAttribute('ts-allow-add');
        }

        return $this;
    }

    /**
     * Returns whether the field is searchable.
     *
     * @return boolean
     */
    public function isSearchable(): bool {
        return $this->hasAttribute('ts-searchable');
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

        if (array_key_exists('searchable', $this->passedProps)) {
            $this->searchable((bool) $this->passedProps['searchable']);
        }
    }
}