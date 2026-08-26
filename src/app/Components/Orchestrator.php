<?php

namespace MM\Meros\App\Components;

use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Checkboxes;
use MM\Meros\App\Components\Fields\Date;
use MM\Meros\App\Components\Fields\Email;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Number;
use MM\Meros\App\Components\Fields\Password;
use MM\Meros\App\Components\Fields\Radio;
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Tel;
use MM\Meros\App\Components\Fields\Text;
use MM\Meros\App\Components\Fields\Time;
use MM\Meros\App\Components\Fields\Url;

use MM\Meros\App\Components\FieldGroups\SimpleContact;

use MM\Meros\Contracts\Orchestrators\ComponentsOrchestrator;

class Orchestrator extends ComponentsOrchestrator {
    private array $fields = [
        'checkbox'   => Checkbox::class,
        'checkboxes' => Checkboxes::class,
        'date'       => Date::class,
        'email'      => Email::class,
        'hidden'     => Hidden::class,
        'number'     => Number::class,
        'password'   => Password::class,
        'radio'      => Radio::class,
        'repeater'   => Repeater::class,
        'select'     => Select::class,
        'tel'        => Tel::class,
        'text'       => Text::class,
        'time'       => Time::class,
        'url'        => Url::class,
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