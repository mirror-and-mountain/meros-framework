<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Livewire\Wireable;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\Facades\Fields as FieldsRegister;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;

use MM\Meros\App\Fields\Repeater;
use MM\Meros\Facades\Framework;

class FormRow extends FeatureDefinition implements Wireable {
    /**
     * Required for FormRows register but not used.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The type of form row, either 'fields' for a row containing individual 
     * fields or 'group' for a row containing a child field group.
     *
     * @var string
     */
    public string $type = '';

    /**
     * The fields that belong to this form row.
     *
     * @var array<Field|array>
     */
    public array $fields = [];

    /**
     * The field group this row belongs to, if any.
     *
     * @var FieldGroup|null
     */
    public ?FieldGroup $parentGroup = null;

    /**
     * The ID of the parent group this row belongs to, if any.
     *
     * @var string|null
     */
    public ?string $parentGroupId = null;

    /**
     * The child field group that belongs to this row, if any.
     *
     * @var FieldGroup|array|null
     */
    public FieldGroup|array|null $group = null;

    /**
     * The ID of the child group that belongs to this row, if any.
     *
     * @var string|null
     */
    public ?string $groupId = null;

    /**
     * The index of this row within its parent group or form, if any.
     *
     * @var int|null
     */
    public ?int $index = null;

    /***************************
     * Feature Contract Methods
     ***************************/

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);

        if (is_array($this->group)) {
            $this->instantiateGroup();
        }

        if (!empty($this->fields)) {
            $this->instantiateFields();
        }
    }

    protected function queue(): void {
        // Form rows don't use the queue method.
    }

    /**
     * Converts the form row instanceinto a format suitable for Livewire rendering.
     *
     * @return array
     */
    public function toLivewire(): array {
        return $this->toJson();
    }

    /**
     * Reconstructs a form row instance from Livewire data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromLivewire($data): self {
        return new static(
            Framework::get(),
            $data
        );
    }

    /**
     * Alias for fromLivewire() to initialize a form row instance from an array of data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function initFromData(array $data): self {
        return self::fromLivewire($data);
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

        $rowIndex = $props['rowIndex'] ?? $this->index;

        $field->row($this, $rowIndex, $position);
        $field->group($this->parentGroup, $this->parentGroupId);

        foreach ($this->fields as $index => $_field) {
            $_field->row($this, $rowIndex, $index);
            $_field->group($this->parentGroup, $this->parentGroupId);
        }

        $this->type = 'fields';
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

        $this->group = $group;
        $this->groupId = $group->id;

        $group->parentRow($this, $this->index);

        $this->type = 'group';
        return $group;
    }

    /**
     * Sets the parent group for this form row.
     *
     * @param FieldGroup|null $group   The parent field group this row belongs to, or null to detach from any group.
     * @param string|null     $groupId Optional ID of the parent group for reference. Not required if $group is provided.
     */
    public function parentGroup(?FieldGroup $group = null, ?string $groupId = null): void {
        $this->parentGroup = $group;
        $this->parentGroupId = $groupId;

        foreach ($this->fields as $field) {
            $field->group($group, $groupId);
        }
    }

    /**
     * Moves a field within the row from one position to another.
     *
     * @param string  $fieldId
     * @param integer $toPosition
     *
     * @return void
     */
    public function moveField(string $fieldId, int $toPosition): void {
        $fieldIndex = collect($this->fields)->search(fn($field) => $field->id === $fieldId);

        if ($fieldIndex === false) {
            return; // Field not found in this row
        }

        $field = $this->fields[$fieldIndex];
        array_splice($this->fields, $fieldIndex, 1); // Remove the field from its current position
        array_splice($this->fields, $toPosition, 0, [$field]); // Insert the field at the new position

        foreach ($this->fields as $index => $field) {
            $field->row($this, $this->index, $index);
            $field->group($this->parentGroup, $this->parentGroupId);
        }
    }

    /**
     * Removes an element from the row by its ID.
     *
     * @param string $elementId
     *
     * @return Field|FieldGroup|null
     */
    public function removeElement(string $elementId): Field|FieldGroup|null {
        $removedElement = Str::startsWith($elementId, 'field-group-')
            ? $this->getGroup()
            : $this->getField($elementId);

        if ($removedElement === null) {
            return null; // Element not found in this row
        }

        if ($this->group !== null && $this->group->id === $elementId) {
            $this->group = null; // Remove the group from the row
            $this->groupId = null;
            $this->type = empty($this->fields) ? '' : 'fields';
            $removedElement->parentRow(null, null); // Detach the group from the row
            return $removedElement;
        }

        $fieldIndex = collect($this->fields)->search(fn($field) => $field->id === $elementId);

        if ($fieldIndex === false) {
            return null; // Field not found in this row
        }

        array_splice($this->fields, $fieldIndex, 1); // Remove the field from the row
        $removedElement->row(null, null, null); // Detach the field from the row
        $removedElement->group(null, null); // Detach group context from the removed field

        foreach ($this->fields as $index => $remainingField) {
            $remainingField->row($this, $this->index, $index);
            $remainingField->group($this->parentGroup, $this->parentGroupId);
        }

        if (empty($this->fields) && $this->group === null) {
            $this->type = '';
        }

        return $removedElement;
    }

    /**
     * Updates the index of the row and notifies all child fields and the child group, if any, of the change.
     *
     * @param int $newIndex The new index of the row.
     *
     * @return void
     */
    public function updateIndex(int $newIndex): void {
        $this->index = $newIndex;

        foreach ($this->fields as $index => $field) {
            $field->row($this, $newIndex, $index);
            $field->group($this->parentGroup, $this->parentGroupId);
        }

        if ($this->group !== null) {
            $this->group->parentRow($this, $newIndex);
        }
    }

    /**
     * Updates the positions of multiple fields within the row based on an associative array of field IDs and new positions.
     *
     * @param array $fieldPositions An associative array where keys are field IDs and values are the new positions.
     *
     * @return void
     */
    public function updateFieldPositions(array $fieldPositions): void {
        foreach ($fieldPositions as $fieldId => $newPosition) {
            $this->moveField($fieldId, $newPosition);
        }
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Returns the number of elements contained in this row, 
     * which may be either individual fields or a child field group.
     *
     * @return int
     */
    public function getElementCount(): int {
        if ($this->group !== null) {
            return 1;
        }

        return count($this->fields);
    }

    /**
     * Instantiates any fields in the row that are provided as an array.
     *
     * @return void
     */
    protected function instantiateFields(): void {
        if (empty($this->fields)) {
            return;
        }

        foreach ($this->fields as $index => $fieldData) {
            if ($fieldData instanceof Field) {
                continue;
            }

            if (!is_array($fieldData) || !isset($fieldData['type'])) {
                continue; // Skip invalid field data
            }

            if ($fieldData['type'] === Repeater::class) {
                $fieldData['properties']['fields'] = $fieldData['fields'] ?? [];
            }

            $field = $fieldData['type']::initFromData($fieldData);

            $field->row($this, $this->index, $index);
            $field->group($this->parentGroup, $this->parentGroupId);

            $this->fields[$index] = $field;
        }
    }

    /**
     * Instantiates the child group if it is provided as an array.
     *
     * @return void
     */
    protected function instantiateGroup(): void {
        if ($this->group === null || $this->group instanceof FieldGroup) {
            return;
        }

        $group = FieldGroup::initFromData($this->group);

        $group->parentRow($this, $this->index);

        $this->group = $group;
    }

    /**
     * Returns whether the row has capacity for more elements.
     * A row may only contain one field group or up to three individual fields.
     *
     * @return bool
     */
    public function hasCapacity(): bool {
        if ($this->group !== null) {
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

        if ($this->group !== null) {
            $childFields = $this->group->getFields();

            if ($asArray) {
                return array_merge($fields->toArray(), $childFields->toArray());
            } else {
                return $fields->merge($childFields);
            }
        }

        return $asArray ? $fields->toArray() : $fields;
    }

    /**
     * Returns a specific field by its ID if it belongs to this row or its child group.
     *
     * @param string $fieldId The ID of the field to retrieve.
     *
     * @return Field|null The field with the specified ID, or null if not found.
     */
    public function getField(string $fieldId): ?Field {
        return $this->getFields()->firstWhere('id', $fieldId);
    }

    /**
     * Returns the elements contained in this row, which may be either individual fields or a child field group.
     *
     * @param bool $asArray Whether to return the fields as an array or a collection. Ignored if the row contains a child group.
     *
     * @return FieldGroup|Collection|array
     */
    public function getElements(bool $asArray = false): FieldGroup|Collection|array {
        if ($this->group !== null) {
            return $this->group;
        }

        return $this->getFields($asArray);
    }

    /**
     * Returns the child field group attached to this row, if any.
     *
     * @return FieldGroup|null
     */
    public function getGroup(): ?FieldGroup {
        return $this->group;
    }

    /**
     * Returns the ID of the child field group attached to this row, if any.
     *
     * @return string|null
     */
    public function getGroupId(): ?string {
        return $this->groupId;
    }

    /**
     * Converts the form row instance into a JSON-serializable array or JSON string.
     *
     * @param boolean $asString
     * @param string  ...$flags
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = ['index' => $this->index];

        if ($this->group !== null) {
            $json['type']    = 'group';
            $json['groupId'] = $this->groupId;
            $json['group']   = $this->group->toJson($asString, ...$flags);
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