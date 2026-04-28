<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Field;
use MM\Meros\Services\Registers\Fields as FieldsRegister;

use MM\Meros\Services\FieldGroup;
use MM\Meros\Services\Registers\FieldGroups as FieldGroupsRegister;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;

trait HasFields {
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
}