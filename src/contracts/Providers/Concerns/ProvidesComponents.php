<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Form;

use MM\Meros\Registers\Components\Fields;
use MM\Meros\Registers\Components\FieldGroups;
use MM\Meros\Registers\Components\Forms;

trait ProvidesComponents {
    use Abstracts;

    /**
     * Retrieves a specific field by handle or returns the fields register.
     *
     * @param string $type Optional. The type of the field to retrieve.
     * 
     * @return Field|Fields|null The requested field or the fields register.
     */
    final protected function fields(string $type = ''): Field|Fields|null {
        return $this->resolveFeatureRequestFor(Field::class, $type);
    }

    /**
     * Retrieves a specific field group by handle or returns the field groups register.
     *
     * @param string $id Optional. The ID of the field group to retrieve.
     * 
     * @return FieldGroup|FieldGroups|null The requested field group or the field groups register.
     */
    final protected function fieldGroups(string $id = ''): FieldGroup|FieldGroups|null {
        return $this->resolveFeatureRequestFor(FieldGroup::class, $id);
    }

    /**
     * Retrieves a specific form by handle or returns the forms register.
     *
     * @param string $id Optional. The ID of the form to retrieve.
     * 
     * @return Form|Forms|null The requested form or the forms register.
     */
    final protected function forms(string $id = ''): Form|Forms|null {
        return $this->resolveFeatureRequestFor(Form::class, $id);
    }
}
