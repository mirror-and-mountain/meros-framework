<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Components\Block;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Contracts\Features\Components\FormAction;

use MM\Meros\Registers\Components\Blocks;
use MM\Meros\Registers\Components\Fields;
use MM\Meros\Registers\Components\FieldGroups;
use MM\Meros\Registers\Components\Forms;
use MM\Meros\Registers\Components\FormActions;

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

    /**
     * Retrieves a specific form action by its name or returns the form actions register.
     *
     * @param string $name Optional. The name of the registered form action to retrieve.
     *
     * @return FormAction|FormActions|null The requested form action or the form actions register.
     */
    final protected function formActions(string $name = ''): FormAction|FormActions|null {
        return $this->resolveFeatureRequestFor(FormAction::class, $name);
    }

    /**
     * Retrieves a specific block by its name or returns the blocks register.
     *
     * @param string $name Optional. The name of the block to retrieve.
     *
     * @return Block|Blocks|null The requested block or the blocks register.
     */
    final protected function blocks(string $name = ''): Block|Blocks|null {
        return $this->resolveFeatureRequestFor(Block::class, $name);
    }
}
