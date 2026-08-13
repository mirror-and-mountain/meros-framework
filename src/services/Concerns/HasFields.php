<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Forms\Form;
use MM\Meros\Services\Registers\Forms as FormsRegister;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Registers\Fields as FieldsRegister;

use MM\Meros\Services\Contracts\Forms\FieldGroup;
use MM\Meros\Services\Registers\FieldGroups as FieldGroupsRegister;

use MM\Meros\Services\Contracts\Forms\FieldWrapper;
use MM\Meros\Services\Registers\FieldWrappers as FieldWrappersRegister;

use MM\Meros\Services\Contracts\Forms\FormAction;
use MM\Meros\Services\Registers\FormActions as FormActionsRegister;

use MM\Meros\Facades\Forms;
use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\FormActions;
use MM\Meros\Facades\FieldWrappers;

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
            return Forms::checkout($this->resolveAuthority());
        }

        else {
            return Forms::get($id, $this->resolveAuthority(), $callback);
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
            return Fields::checkout($this->resolveAuthority());
        }

        else {
            return Fields::get($handle, $this->resolveAuthority(), $callback);
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
            return FieldGroups::checkout($this->resolveAuthority());
        }

        else {
            return FieldGroups::get($handle, $this->resolveAuthority(), $callback);
        }
    }

    /**
     * Retrieves a form style by handle or returns the form styles register if no handle is provided.
     *
     * @param string       $handle The handle of the form style to retrieve. If empty, the entire form styles register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return FieldWrapper|FieldWrappersRegister|null The requested field wrapper, the field wrappers register, or null if not found.
     */
    protected function fieldWrappers(string $handle = '', ?Closure $callback = null): FieldWrapper|FieldWrappersRegister|null {
        if (empty($handle)) {
            return FieldWrappers::checkout($this->resolveAuthority());
        }

        else {
            return FieldWrappers::get($handle, $this->resolveAuthority(), $callback);
        }
    }

    /**
     * Retrieves a form action by handle or returns the form actions register if no handle is provided.
     *
     * @param string       $handle   The handle of the form action to retrieve. If empty, the entire form actions register is returned.
     * @param Closure|null $callback An optional callback used in the register's get() method.
     *
     * @return FormAction|FormActionsRegister|null
     */
    protected function formActions(string $handle = '', ?Closure $callback = null): FormAction|FormActionsRegister|null {
        if (empty($handle)) {
            return FormActions::checkout($this->resolveAuthority());
        }

        else {
            return FormActions::get($handle, $this->resolveAuthority(), $callback);
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
     * Alias of the fieldWrappers() method for users who prefer the snake_case naming convention.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return FieldWrapper|FieldWrappersRegister|null
     */
    protected function field_wrappers(string $handle = '', ?Closure $callback = null): FieldWrapper|FieldWrappersRegister|null {
        return $this->fieldWrappers($handle, $callback);
    }

    /**
     * Alias of the formActions() method for users who prefer the snake_case naming convention.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return FormAction|FormActionsRegister|null
     */
    protected function form_actions(string $handle = '', ?Closure $callback = null): FormAction|FormActionsRegister|null {
        return $this->formActions($handle, $callback);
    }
}