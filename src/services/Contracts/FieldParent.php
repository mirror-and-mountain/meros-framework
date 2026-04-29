<?php 

namespace MM\Meros\Services\Contracts;

use MM\Meros\Services\Contracts\FieldGroup;
use MM\Meros\App\Fields\Repeater;

interface FieldParent {
    /**
     * Attaches a field or an array of fields to the parent.
     *
     * @var FieldGroup|Repeater|null
     */
    public function attach(Field|array $field): self;
}