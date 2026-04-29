<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Form;
use MM\Meros\Services\Registers\Forms as FormsRegister;

use MM\Meros\Services\Contracts\Field;
use MM\Meros\Services\Registers\Fields as FieldsRegister;

use MM\Meros\Services\Contracts\FieldGroup;
use MM\Meros\Services\Registers\FieldGroups as FieldGroupsRegister;

use MM\Meros\Services\Contracts\FieldStyle;
use MM\Meros\Services\Registers\FieldStyles as FieldStylesRegister;

use MM\Meros\Facades\Forms;
use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\FieldStyles;

trait HasFields {
    /**
     * Retrieves a form by ID or returns the forms register if no ID is provided.
     *
     * @param string       $id The ID of the form to retrieve. If empty, the entire forms register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return Form|FormsRegister|null The requested form, the forms register, or null if not found.
     */
    protected function forms(string $id = '', ?Closure $callback = null): Form|FormsRegister|null {
        if (empty($id)) {
            return Forms::checkout($this);
        }

        else {
            return Forms::checkout($this)->get($id, $callback);
        }
    }

    /**
     * Retrieves a field by handle or returns the fields register if no handle is provided.
     *
     * @param string       $handle The handle of the field to retrieve. If empty, the entire fields register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return Field|FieldsRegister|null The requested field, the fields register, or null if not found.
     */
    protected function fields(string $handle = '', ?Closure $callback = null): Field|FieldsRegister|null {
        if (empty($handle)) {
            return Fields::checkout($this);
        }

        else {
            return Fields::checkout($this)->get($handle, $callback);
        }
    }

    /**
     * Retrieves a field group by handle or returns the field groups register if no handle is provided.
     *
     * @param string       $handle The handle of the field group to retrieve. If empty, the entire field groups register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return FieldGroup|FieldGroupsRegister|null The requested field group, the field groups register, or null if not found.
     */
    protected function fieldGroups(string $handle = '', ?Closure $callback = null): FieldGroup|FieldGroupsRegister|null {
        if (empty($handle)) {
            return FieldGroups::checkout($this);
        }

        else {
            return FieldGroups::checkout($this)->get($handle, $callback);
        }
    }

    /**
     * Retrieves a field style by handle or returns the field styles register if no handle is provided.
     *
     * @param string       $handle The handle of the field style to retrieve. If empty, the entire field styles register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return FieldStyle|FieldStylesRegister|null The requested field style, the field styles register, or null if not found.
     */
    protected function fieldStyles(string $handle = '', ?Closure $callback = null): FieldStyle|FieldStylesRegister|null {
        if (empty($handle)) {
            return FieldStyles::checkout($this);
        }

        else {
            return FieldStyles::checkout($this)->get($handle, $callback);
        }
    }

    /*********************
     * Aliases
     *********************/

    /**
     * Alias of the fieldGroups() method for users who prefer the snake_case naming convention.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return FieldGroup|FieldGroupsRegister|null
     */
    protected function field_groups(string $handle = '', ?Closure $callback = null): FieldGroup|FieldGroupsRegister|null {
        return $this->fieldGroups($handle, $callback);
    }

    /**
     * Alias of the fieldStyles() method for users who prefer the snake_case naming convention.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return FieldStyle|FieldStylesRegister|null
     */
    protected function field_styles(string $handle = '', ?Closure $callback = null): FieldStyle|FieldStylesRegister|null {
        return $this->fieldStyles($handle, $callback);
    }
}