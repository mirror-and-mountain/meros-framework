<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\Facades\Fields as FieldsRegister;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;

class FormRow extends FeatureDefinition {
    
    /**
     * Required for FormRows register but not used.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The fields that belong to this form row.
     *
     * @var array<Field>
     */
    protected array $fields = [];

    /**
     * The field group this row belongs to, if any.
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $parentGroup = null;

    /**
     * The child field group that belongs to this row, if any.
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $childGroup = null;

    /***************************
     * Feature Contract Methods
     ***************************/

    protected function queue(): void {
        // Form rows don't use the queue method.
    }


    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Attaches a field to the form row. A row can contain up to three fields or one child group.
     *
     * @param Field|string        $field    A Field instance or a registered field identifier to attach to the row.
     * @param Closure|array|null  $callback Optional callback to configure the field if a registered identifier is provided.
     * @param array               $props    Optional properties for the field if a registered identifier is provided.
     *
     * @return Field
     * @throws \LogicException If the row already has maximum number of fields or a child group.
     */
    public function field(Field|string $field, Closure|array|null $callback = null, array $props = []): Field {
        if (!$this->hasCapacity()) {
            throw new \LogicException("Cannot attach field: row already has maximum number of fields or a child group.");
        }
    
        $params = func_num_args();

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }
    
        if (is_string($field)) {
            $field = FieldsRegister::checkout($this->provider)
                ->makeFrom($field, $callback, $props);
        }

        else {
            $field = FieldsRegister::checkout($this->provider)
                ->attach($field);
        }

        $position = isset($props['position']) ? (int)$props['position'] : count($this->fields) + 1;

        if ($position > 2) {
            $position = 2;
        }

        $this->fields = array_merge(
            array_slice($this->fields, 0, $position),
            [$field],
            array_slice($this->fields, $position)
        );

        $field->position($position);
        $field->row($this);

        return $field;
    }

    /**
     * Attaches a field group to the form row. A row can contain up to three fields or one child group.
     *
     * @param FieldGroup|string|Closure|null  $group    A FieldGroup instance or a registered field group identifier to attach to the row. Null to create an empty group.
     * @param Closure|array|null              $callback Optional callback to configure the field group if a registered identifier is provided.
     * @param array                           $props    Optional properties for the field group if a registered identifier is provided.
     *
     * @return FieldGroup
     * @throws \LogicException If the row already has maximum number of fields or a child group.
     */
    public function group(FieldGroup|string|Closure|null $group = null, Closure|array|null $callback = null, array $props = []): FieldGroup {
        if (!$this->hasCapacity()) {
            throw new \LogicException("Cannot attach group: row already has maximum number of fields or a child group.");
        }
    
        $params = func_num_args();

        if ($params === 1 && $group instanceof Closure) {
            $callback = $group;
            $group    = null;
        }

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        if ($group === null) {
            $group = FieldGroupsRegister::checkout($this->provider)->make($callback);
        }

        else if (is_string($group)) {
            $group = FieldGroupsRegister::checkout($this->provider)
                ->makeFrom($group, $callback, $props);
        }

        else {
            $group = FieldGroupsRegister::checkout($this->provider)
                ->attach($group);
        }

        $this->childGroup = $group;
        return $group;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Returns whether the row has capacity for more elements.
     * A row may only contain one field group or up to three individual fields.
     *
     * @return bool
     */
    public function hasCapacity(): bool {
        if ($this->childGroup !== null) {
            return false;
        }

        if (count($this->fields) === 3) {
            return false;
        }

        return true;
    }

    /**
     * Returns the fields that belong to this row as a collection or array.
     * Includes fields from a child group if one exists.
     * 
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array 
     */
    public function getFields(bool $asArray = false): Collection|array {
        $fields = collect($this->fields);

        if ($this->childGroup !== null) {
            $childFields = $this->childGroup->getFields();

            if ($asArray) {
                return array_merge($fields->toArray(), $childFields->toArray());
            } else {
                return $fields->merge($childFields);
            }
        }

        return $asArray ? $fields->toArray() : $fields;
    }

    /**
     * Returns the elements contained in this row, which may be either individual fields or a child field group.
     *
     * @param bool $asArray Whether to return the fields as an array or a collection. Ignored if the row contains a child group.
     *
     * @return FieldGroup|Collection|array
     */
    public function getForms(bool $asArray = false): FieldGroup|Collection|array {
        if ($this->childGroup !== null) {
            return $this->childGroup;
        }

        return $this->getFields($asArray);
    }

    
    /**
     * Returns the child field group attached to this row, if any.
     *
     * @return FieldGroup|null
     */
    public function getChildGroup(): ?FieldGroup {
        return $this->childGroup;
    }

    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = [];

        if ($this->childGroup !== null) {
            $json['type']  = 'group';
            $json['group'] = $this->childGroup->toJson($asString, ...$flags);
        }

        else {
            $json['type']   = 'fields';
            $json['fields'] = array_map(fn($field) => $field->toJson(), $this->fields);
        }

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }
}