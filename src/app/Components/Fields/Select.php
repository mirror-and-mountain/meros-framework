<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

class Select extends Field {
    use HasOptions;
    
    final protected function init(): void {
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
}