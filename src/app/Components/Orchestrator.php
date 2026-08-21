<?php

namespace MM\Meros\App\Components;

use MM\Meros\App\Components\Fields\Text;
use MM\Meros\App\Components\Fields\Number;
use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Email;
use MM\Meros\App\Components\FieldGroups\SimpleContact;

use MM\Meros\Contracts\Orchestrators\ComponentsOrchestrator;

class Orchestrator extends ComponentsOrchestrator {
    private array $fields = [
        'text'     => Text::class,
        'number'   => Number::class,
        'checkbox' => Checkbox::class,
        'email'    => Email::class,
    ];

    private array $fieldGroups = [
        'simple-contact-fields' => SimpleContact::class,
    ];

    protected function configure(): void {
        foreach ($this->fields as $alias => $fieldClass) {
            $this->fields()->register($fieldClass, $alias);
        }

        foreach ($this->fieldGroups as $alias => $groupClass) {
            $this->fieldGroups()->register($groupClass, $alias);
        }
    }
}