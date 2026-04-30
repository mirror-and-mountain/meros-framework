<?php 

namespace MM\Meros\Services\Contracts\Elements\Interfaces;

use MM\Meros\App\Fields\Repeater;
use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\FieldGroup;

interface FieldParent {
    /**
     * Attaches a field or an array of fields to the parent.
     *
     * @var FieldGroup|Repeater|null
     */
    public function attach(Field|array $field): self;
}