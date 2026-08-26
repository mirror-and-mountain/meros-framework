<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\Concerns\HasOptions;

abstract class Choice extends Field {
    use HasOptions;
    
    final protected function init(): void {
        parent::init();
        $this->wrapper('site', '');
        $this->wrapper('settings', '');
        $this->view('meros::forms.fields.choice');
        
        $this->setSerializableProperties(['allowsMultiple', 'options']);
        $this->addSupports(['multiple']);
    }
}