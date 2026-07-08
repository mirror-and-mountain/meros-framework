<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use MM\Meros\Support\MergeFields;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\DynamicChoiceSources as DynamicChoiceSourcesAccessor;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\FormActions;

use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\PostMeta as FormMeta;

use MM\Meros\Services\Contracts\Forms\FormAction;

class Builder extends Component {
    /**
     * Nav Items to be rendered in the builder's navigation bar.
     *
     * @var array
     */
    public array $navItems = [];

    /**
     * The url to return to when clicking the wordpress link in the header.
     *
     * @var string
     */
    public string $returnUrl = '';

    /**
     * The mode of the form builder.
     *
     * @var string
     */
    public string $mode = 'public-form';

    /**
     * The current screen being displayed in the builder.
     *
     * @var string
     */
    public string $screen = 'canvas-main';

    /**
     * The form model.
     *
     * @var Form|null
     */
    public ?Form $form = null;

    /**
     * The ID of the form being edited, or null if creating a new form.
     *
     * @var string|int|null
     */
    public string|int|null $formID = null;

    /**
     * Form settings, including title, description, slug and status.
     *
     * @var array
     */
    public array $formSettings = [];

    /**
     * The available field types for the builder.
     *
     * @var array
     */
    public array $fieldTypes = [];

    /**
     * The available field categories for the builder.
     *
     * @var array
     */
    public array $fieldCategories = [];

    /**
     * The available field groups for the builder.
     *
     * @var array
     */
    public array $fieldGroups = [];

    /**
     * The form schema.
     *
     * @var array
     */
    public array $schema = [];

    /**
     * Array of the form's row objects.
     *
     * @var array<FormRow>
     */
    public array $rows = [];

    /**
     * The field instance currently active for editing (if any).
     *
     * @var Field|null
     */
    public ?Field $activeField = null;

    /**
     * The ID of the currently active field.
     *
     * @var string|null
     */
    public ?string $activeFieldId = null;

    /**
     * The repeater field currently being edited in the repeater editor screen (if any).
     *
     * @var Repeater|null
     */
    public ?Repeater $activeRepeater = null;

    /**
     * The ID of the currently active repeater field.
     *
     * @var string|null
     */
    public ?string $activeRepeaterId = null;

    /**
     * Return-screen targets keyed by screen name.
     *
     * @var array<string,string>
     */
    public array $screenReturnTargets = [];

    /**
     * Version counter used to force remounting the repeater editor subtree.
     *
     * @var int
     */
    public int $repeaterEditorVersion = 0;

    /**
     * Version counter used to force remounting field-settings subtrees.
     *
     * @var int
     */
    public int $fieldSettingsVersion = 0;

    public function mount(string|int|null $formID = null) {
        $this->initialiseFieldTypes();
        $this->initialiseFieldGroups();
        // $this->initialiseFormActions();

        if ($formID) {
            $this->formID = $formID;
            $this->form   = Form::find($formID);

            $this->navItems = [
                'settings-main' => 'Settings',
                'canvas-main'   => 'Canvas',
                'preview'       => get_preview_post_link($formID)
            ];

            $this->returnUrl = admin_url('edit.php?post_type=meros-form');

            $this->loadFormSchema();

            foreach ($this->rows as $index => $row) {
                if (!$row instanceof FormRow) {
                    $this->rows[$index] = FormRow::initFromData(array_merge($row, ['index' => $index]));
                }
            }
        } 
        
        else {
            $this->makeNewForm();
        }
    }

    public function render() {
        return view('meros::toolbox.forms.builder.index', [
            'mode'            => $this->mode,
            'screen'          => $this->screen,
            'navItems'        => $this->navItems,
            'returnUrl'       => $this->returnUrl,
            'formID'          => $this->formID,
            'formTitle'       => $this->formSettings['title'] ?? '',
            'formDescription' => $this->formSettings['description'] ?? '',
            'formSlug'        => $this->formSettings['slug'] ?? '',
            'formStatus'      => $this->formSettings['status'] ?? '',
            'dynamicChoiceSources' => $this->getDynamicChoiceSourcesForBuilder(),
        ])
            ->layout('meros::toolbox.layout');
    }

    // =========================================================================
    // Initialisation Methods
    // =========================================================================

    /**
     * Initialises form settings and schema from the form model, if it exists, 
     * or sets defaults for a new form.
     *
     * @return void
     */
    private function loadFormSchema(): void {
        if (!$this->form) {
            return;
        }

        $this->formSettings = [
            'title'       => $this->form->post_title ?? '',
            'description' => $this->form->post_content ?? '',
            'slug'        => $this->form->post_name ?? '',
            'status'      => $this->form->post_status ?? '',
        ];

        $schema = is_array($this->form->schema) ? $this->form->schema : json_decode($this->form->schema, true);

        $this->schema = is_array($schema) ? $schema : [
            'rows'    => [],
            'actions' => []
        ];

        $this->rows = $this->schema['rows'] ?? [];
    }

    /**
     * Initialises available field types.
     *
     * @return void
     */
    private function initialiseFieldTypes(): void {
        foreach (Fields::getRegistered() as $handle => $fieldType) {
            if (!$fieldType::$showInFormBuilder) {
                continue;
            }

            $this->fieldTypes[ $handle ] = $fieldType;
            $category                    = $fieldType::getCategory();

            $this->fieldCategories[ $category ][ $handle ] = [
                'handle' => $handle,
                'class'  => $fieldType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
                'icon'   => $fieldType::getIcon(),
             ];
        }
    }

    /**
     * Initialises available field groups.
     *
     * @return void
     */
    private function initialiseFieldGroups(): void {
        foreach (FieldGroups::getRegistered() as $handle => $_) {
            $this->fieldGroups[ $handle ] = Str::title(Str::replace(['-', '_'], ' ', $handle));
        }
    }

    // =========================================================================
    // Schema Update Methods
    // =========================================================================

    /**
     * Handles canvas events emitted from Alpine, routing them to the relevant handler method based on the event type.
     *
     * @param string $event
     * @param array  $payload
     *
     * @return void
     */
    public function handleCanvasEvent(string $event, array $payload = []): void {
        switch ($event) {
            case 'insert-element-into-new-row':
                $this->insertElementIntoNewRow(
                    $payload['elementType'],
                    $payload['elementHandle'],
                    $payload['rowIndex'],
                    $payload['destinationGroupId'] ?? null
                );
                break;

            case 'insert-element-into-existing-row':
                $this->insertElementIntoExistingRow(
                    $payload['elementType'],
                    $payload['elementHandle'],
                    $payload['rowIndex'],
                    $payload['fieldPosition'],
                    $payload['destinationGroupId'] ?? null
                );
                break;

            case 'move-field-in-current-row':
                $this->moveFieldInCurrentRow(
                    $payload['fieldId'],
                    $payload['rowIndex'],
                    $payload['toRowPosition'],
                    $payload['currentGroupId'] ?? null,
                );
                break;

            case 'move-field-to-existing-row':
                $this->moveFieldToExistingRow(
                    $payload['fieldId'],
                    $payload['fromRowIndex'],
                    $payload['toRowIndex'],
                    $payload['toRowPosition'],
                    $payload['currentGroupId'] ?? null,
                    $payload['destinationGroupId'] ?? null
                );
                break;

            case 'move-field-to-new-row':
            case 'move-group-to-new-row':
                $id = $payload['fieldId'] ?? $payload['groupId'] ?? null;

                $this->moveElementToNewRow(
                    $id,
                    $payload['fromRowIndex'],
                    $payload['toRowIndex'],
                    $payload['currentGroupId'] ?? null,
                    $payload['destinationGroupId'] ?? null
                );
                break;

            case 'insert-field-into-repeater':
                $this->addRepeaterField(
                    $payload['fieldHandle'],
                    $payload['fieldPosition']
                );
                break;

            case 'move-repeater-field':
                $this->moveRepeaterField(
                    $payload['fieldId'],
                    $payload['toPosition']
                );
                break;
        }

        $this->dispatch('mforms:form-canvas-updated');
    }

    /**
     * Inserts a new element into the form schema at a specific row index, shifting existing rows down.
     *
     * @param string      $elementType
     * @param string      $elementHandle
     * @param int         $rowIndex
     * @param string|null $destinationGroupId Optional group ID if inserting into a group row.
     *
     * @return void
     */
    private function insertElementIntoNewRow(
        string  $elementType, 
        string  $elementHandle, 
        int     $rowIndex,
        ?string $destinationGroupId = null,
    ): void {
        if ($elementType === 'field') {
            if (!in_array($elementHandle, array_keys($this->fieldTypes))) {
                return;
            }

            $rowIndex = $rowIndex === -1 ? 0 : $rowIndex;

            $row = FormRow::initFromData([
                'index' => $rowIndex,
            ]);

            if ($row !== null) {
                $row->field($elementHandle);
                $this->insertRowAt($row, $rowIndex, $destinationGroupId);
            }
        }

        else if ($elementType === 'group') {
            if (!in_array($elementHandle, array_merge(array_keys($this->fieldGroups), ['untitled_section']))) {
                return;
            }

            // Groups cannot be inserted into group rows.
            if ($destinationGroupId !== null) {
                return;
            }

            $rowIndex = $rowIndex === -1 ? 0 : $rowIndex;

            $row = FormRow::initFromData([
                'index' => $rowIndex,
            ]);

            if ($row === null) {
                return;
            }

            $row->group($elementHandle === 'untitled_section' ? null : $elementHandle);
            $this->insertRowAt($row, $rowIndex, null);
        }
    }

    /**
     * Inserts a new element into an existing row in the form schema at a specific field position.
     *
     * @param string      $elementType
     * @param string      $elementHandle
     * @param int         $rowIndex
     * @param int         $fieldPosition
     * @param string|null $destinationGroupId Optional group ID if inserting into a group row.
     *
     * @return void
     */
    private function insertElementIntoExistingRow(
        string  $elementType, 
        string  $elementHandle, 
        int     $rowIndex, 
        int     $fieldPosition,
        ?string $destinationGroupId = null
    ): void {
        if (!in_array($elementHandle, array_keys($this->fieldTypes))) {
            return;
        }

        if ($elementType === 'field') {
            $row = $this->getRow($rowIndex, $destinationGroupId);

            if ($row === null) {
                return;
            }

            $row->field($elementHandle, ['position' => $fieldPosition]);
        }
    }

    /**
     * Moves a field within the same row to a new position.
     *
     * @param string      $fieldId
     * @param int         $rowIndex
     * @param int         $toPosition
     * @param string|null $currentGroupId Optional group ID if moving within a group row.
     *
     * @return void
     */
    private function moveFieldInCurrentRow(
        string  $fieldId, 
        int     $rowIndex, 
        int     $toPosition,
        ?string $currentGroupId = null,
    ): void {
        $row = $this->getRow($rowIndex, $currentGroupId);
        
        if ($row !== null) {
            $row->moveField($fieldId, $toPosition);
        }
    }

    /**
     * Moves a field from one row to another existing row at a specific position.
     *
     * @param string      $fieldId
     * @param int         $originRowIndex
     * @param int         $destinationRowIndex
     * @param int         $destinationFieldPosition
     * @param string|null $currentGroupId Optional group ID if moving from a group row.
     * @param string|null $destinationGroupId Optional group ID if moving to a group row.
     *
     * @return void
     */
    private function moveFieldToExistingRow(
        string  $fieldId, 
        int     $originRowIndex, 
        int     $destinationRowIndex, 
        int     $destinationFieldPosition,
        ?string $currentGroupId = null,
        ?string $destinationGroupId = null
    ): void {
        $originRow = $this->getRow($originRowIndex, $currentGroupId);
        $destinationRow = $this->getRow($destinationRowIndex, $destinationGroupId);

        if ($originRow === null || $destinationRow === null) {
            return;
        }

        $field = $originRow->removeElement($fieldId);

        if ($field !== null) {
            $destinationRow->field($field, ['position' => $destinationFieldPosition]);
        }

        if ($originRow->getElementCount() === 0) {
            $this->removeRowAt($originRowIndex, $currentGroupId);
        }
    }

    /**
     * Moves an element (field or group) from one row to a new row at a specific index.
     *
     * @param string      $elementId
     * @param int         $originRowIndex
     * @param int         $destinationRowIndex
     * @param string|null $currentGroupId Optional group ID if moving from a group row.
     * @param string|null $destinationGroupId Optional group ID if moving to a group row.
     *
     * @return void
     */
    private function moveElementToNewRow(
        string  $elementId,
        int     $originRowIndex,
        int     $destinationRowIndex,
        ?string $currentGroupId = null,
        ?string $destinationGroupId = null
    ): void {
        $destinationRowIndex = $destinationRowIndex === -1 ? 0 : $destinationRowIndex;
        $originRow = $this->getRow($originRowIndex, $currentGroupId);

        if ($originRow === null) {
            return;
        }

        $element = $originRow->removeElement($elementId);

        if ($element !== null) {
            $newRow = FormRow::initFromData([
                'index' => $destinationRowIndex,
            ]);

            if ($newRow === null) {
                return;
            }

            if ($element instanceof Field) {
                $newRow->field($element);
            } else if ($element instanceof FieldGroup) {
                $newRow->group($element);
            }

            $this->insertRowAt($newRow, $destinationRowIndex, $destinationGroupId);

            if ($originRow->getElementCount() === 0) {
                $isSameContainer = $currentGroupId === $destinationGroupId;

                if ($isSameContainer && $originRowIndex >= $destinationRowIndex) {
                    $originRowIndex++;
                }

                $this->removeRowAt($originRowIndex, $currentGroupId);
            }
        }
    }

    /**
     * Removes a field from a specific row, and removes the row if it becomes empty.
     *
     * @param string      $fieldId
     * @param int         $rowIndex
     * @param string|null $groupId Optional group ID if removing from a group row.
     *
     * @return void
     */
    public function removeField(string $fieldId, int $rowIndex, ?string $groupId = null): void {
        if ($this->screen === 'canvas-repeater-editor') {
            $this->removeRepeaterField($fieldId);
            $this->reHydrateScreen();
            $this->dispatch('mforms:close-removed-field-settings', $fieldId);
            $this->dispatch('mforms:form-canvas-updated');
            return;
        }
    
        $row = $this->getRow($rowIndex, $groupId);

        if ($row === null) {
            return;
        }

        $row->removeElement($fieldId);

        if ($row->getElementCount() === 0) {
            $this->removeRowAt($rowIndex, $groupId);
        }

        $this->dispatch('mforms:close-removed-field-settings', $fieldId);
        $this->dispatch('mforms:form-canvas-updated');
    }

    /**
     * Removes a group from a specific row, and removes the row if it becomes empty.
     *
     * @param string $groupId
     * @param int    $rowIndex
     *
     * @return void
     */
    public function removeGroup(string $groupId, int $rowIndex): void {
        $row = $this->getRow($rowIndex);

        if ($row === null) {
            return;
        }

        $row->removeElement($groupId);

        if ($row->getElementCount() === 0) {
            $this->removeRowAt($rowIndex);
        }

        $this->dispatch('mforms:form-canvas-updated');
    }

    // =========================================================================
    // Field Update Methods
    // =========================================================================

    /**
     * Sets the currently active field.
     *
     * @param string      $fieldId
     * @param int         $rowIndex
     * @param string|null $groupId Optional group ID if setting from a group row.
     *
     * @return array|null
     */
    public function setActiveField(string $fieldId, int $rowIndex, ?string $groupId = null): ?array {
        $field = $this->getField($fieldId, $rowIndex, $groupId);

        if ($field === null) {
            return null;
        }

        $this->activeFieldId = $fieldId;
        $this->activeField = $field;

        
        $this->reHydrateScreen();
        return $this->getActiveField();
    }

    /**
     * Retrieves the currently active field's properties as an array, or null if no field is active.
     *
     * @return array|null
     */
    public function getActiveField(): ?array {
        if ($this->activeField === null) {
            return null;
        }

        $this->reHydrateScreen();
        return $this->activeField->toJson()['properties'] ?? null;
    }

    /**
     * Clears the currently active field.
     *
     * @return void
     */
    #[Renderless]
    public function clearActiveField(): void {
        $this->activeFieldId = null;
        $this->activeField = null;
    }

    /**
     * Updates a specific property of the currently active field.
     *
     * @param string      $property
     * @param mixed       $value
     * @param int|null    $rowIndex
     * @param string|null $groupId
     *
     * @return void
     */
    #[Renderless]
    public function updateActiveFieldProperty(string $property, mixed $value, ?int $rowIndex = null, ?string $groupId = null): void {
        if ($this->activeField === null) {
            return;
        }

        $canonicalField = $this->resolveActiveField();

        if ($canonicalField === null) {
            return;
        }

        if (str_contains($property, '.')) {
            [$root, $key] = explode('.', $property, 2);

            if ($root === 'attributes' && $key !== '' && method_exists($canonicalField, 'attribute')) {
                $canonicalField->attribute($key, $value);

                if ($key === 'required' && $value === true) {
                    $canonicalField->disabled(false);
                }

                if ($key === 'disabled' && $value === true) {
                    $canonicalField->required(false);
                }

                $this->activeField = $canonicalField;
                $this->syncRepeaterEditorAfterActiveFieldUpdate();
                $this->dispatchFieldSettingsUpdated();
                return;
            }
        }

        if ($property === 'rule' && is_array($value)) {
            $rule = $value['rule'] ?? null;
            $value = $value['value'] ?? null;

            if ($rule !== null) {
                $canonicalField->rule($rule, $value);
                $this->activeField = $canonicalField;
                $this->syncRepeaterEditorAfterActiveFieldUpdate();
                $this->dispatchFieldSettingsUpdated();
                return;
            }
        }

        if ($property === 'rules' && is_array($value)) {
            $canonicalField->rules(Arr::keyBy($value, 'rule'));
            
            $this->activeField = $canonicalField;
            $this->syncRepeaterEditorAfterActiveFieldUpdate();
            $this->dispatchFieldSettingsUpdated();
            return;
        }

        if (!property_exists($canonicalField, $property) && !method_exists($canonicalField, $property)) {
            return;
        }

        // Prefer method if possible to allow for any formatting applied to the property via that method.
        if (method_exists($canonicalField, $property)) {
            $canonicalField->{$property}($value);
        } else {
            $canonicalField->{$property} = $value;
        }

        $this->activeField = $canonicalField;
        $this->syncRepeaterEditorAfterActiveFieldUpdate();
        $this->dispatchFieldSettingsUpdated();
    }

    public function getDynamicDefaultControl(): ?Field {
        if ($this->activeField === null) {
            return null;
        }
        
        $control = MergeFields::get()->toField(
            $this->activeField->getExactDataType(),
            $this->activeField->getType(),
            method_exists($this->activeField, 'getDynamicOptionsSource')
            && method_exists($this->activeField, 'usesDynamicOptions')
            && $this->activeField->usesDynamicOptions()
                ? $this->activeField->getDynamicOptionsSource()
                : null,
            'field-dynamic-default', 
            'field_dynamic_default',
            'Dynamic Default Value',
            'Select a merge field to use as the default value for this field.'
        );

        $control->default($this->activeField->getDynamicDefaultType() ?? '');
        $control->allowAdd();

        return $control;
    }

    /**
     * Dispatches field-settings update events used by Alpine to resync UI state,
     * and increments a version counter used by keyed controls.
     *
     * @return void
     */
    private function dispatchFieldSettingsUpdated(): void {
        $this->fieldSettingsVersion++;
        $this->dispatch('mforms:refresh-field-settings');
    }

    /**
     * Triggers repeater-editor refresh when updating a repeater subfield.
     *
     * @return void
     */
    private function syncRepeaterEditorAfterActiveFieldUpdate(): void {
        if (!$this->hasActiveRepeaterContext()) {
            return;
        }

        $repeater = $this->resolveEditingRepeater();

        if ($repeater !== null) {
            $this->activeRepeater = $repeater;
        }

        $this->dispatchRepeaterEditorUpdated();
    }

    // =========================================================================
    // Schema Update Helpers
    // =========================================================================

    /**
     * Retrieves a row by its index, optionally within a specific group.
     *
     * @param int         $rowIndex
     * @param string|null $groupId Optional group ID if retrieving from a group row.
     *
     * @return FormRow|null
     */
    private function getRow(int $rowIndex, ?string $groupId = null): ?FormRow {
        $groupId = $groupId !== null && trim($groupId) !== '' ? $groupId : null;

        if ($groupId !== null) {
            $group = $this->getRowGroup($groupId);
            
            if ($group !== null) {
                return $group->rows[$rowIndex] ?? null;
            }
        }

        return $this->rows[$rowIndex] ?? null;
    }

    /**
     * Inserts a row at a specific index, optionally within a specific group, and updates subsequent row indexes.
     *
     * @param FormRow     $row
     * @param int         $rowIndex
     * @param string|null $groupId Optional group ID if inserting into a group row.
     *
     * @return void
     */
    private function insertRowAt(FormRow $row, int $rowIndex, ?string $groupId = null): void {
        $groupId = $groupId !== null && trim($groupId) !== '' ? $groupId : null;

        if ($groupId !== null) {
            $group = $this->getRowGroup($groupId);

            if ($group === null) {
                return;
            }

            $group->row($row, $rowIndex);
            return;
        }

        array_splice($this->rows, $rowIndex, 0, [$row]);
        $this->updateRowIndexes($this->rows);
    }

    /**
     * Removes a row at a specific index, optionally within a specific group, and updates subsequent row indexes.
     *
     * @param int         $rowIndex
     * @param string|null $groupId Optional group ID if removing from a group row.
     *
     * @return void
     */
    private function removeRowAt(int $rowIndex, ?string $groupId = null): void {
        $groupId = $groupId !== null && trim($groupId) !== '' ? $groupId : null;

        if ($groupId !== null) {
            $group = $this->getRowGroup($groupId);

            if ($group === null) {
                return;
            }

            array_splice($group->rows, $rowIndex, 1);
            $this->updateRowIndexes($group->rows);
            return;
        }

        array_splice($this->rows, $rowIndex, 1);

        $this->updateRowIndexes($this->rows);
    }

    /**
     * Updates the indexes of a set of rows to ensure they are sequential after an insertion or removal.
     *
     * @param array $rows
     *
     * @return void
     */
    private function updateRowIndexes(array $rows): void {
        foreach ($rows as $index => $row) {
            $row->updateIndex($index);
        }
    }

    /**
     * Retrieves a group instance by its ID.
     *
     * @param string $groupId
     *
     * @return FieldGroup|null
     */
    private function getRowGroup(string $groupId): ?FieldGroup {
        return collect($this->rows)
            ->where('type', 'group')
            ->where('groupId', $groupId)
            ->first()?->getGroup();
    }

    /**
     * Retrieves a field instance by its ID and row index, optionally within a specific group.
     *
     * @param string      $fieldId
     * @param integer     $rowIndex
     * @param string|null $groupId
     *
     * @return Field|null
     */
    private function getField(string $fieldId, int $rowIndex, ?string $groupId = null): ?Field {
        if ($this->isRepeaterEditorScreen()) {
            $repeater = $this->resolveEditingRepeater();

            if ($repeater === null) {
                return null;
            }

            $field = $repeater->getFields()->where('id', $fieldId)->first();

            if ($field !== null) {
                return $field;
            }

            return null;
        }
    

        $row = $this->getRow($rowIndex, $groupId);

        if ($row === null) {
            return null;
        }

        return $row->getField($fieldId);
    }

    /**
     * Retrieves all field instances from the form schema as a collection or an array.
     * 
     * @param bool  $skipRepeaterFields Whether to skip fields that are part of a repeater when retrieving all fields.
     * @param bool  $asArray            Whether to return the fields as an array (true) or a collection (false).
     * @param array $pluckKeys          Optional array of property keys to pluck from each field's properties when returning as an array.
     *
     * @return Collection|array
     */
    #[Renderless]
    public function getFields(bool $skipRepeaterFields = false, bool $asArray = false,  array $pluckKeys = []): Collection|array {
        $fields = collect($this->rows)
            ->filter(fn($row) => $row instanceof FormRow)
            ->flatMap(function (FormRow $row) use ($skipRepeaterFields) {
                $fields = $row->getFields();

                if (!$skipRepeaterFields) {
                    $fields = $fields->map(function (Field $field) {
                        if ($field instanceof Repeater) {
                            return $field->getFields();
                        }
                        return $field;
                    })->flatten();
                }

                return $fields;
            });

        if ($asArray && empty($pluckKeys)) {
            $fields = $fields->map(function (Field $field) {
                return array_merge(['handle' => $field->handle], $field->toJson()['properties'] ?? []);
            });
        } 
        
        else if ($asArray && !empty($pluckKeys)) {
            $fields = $fields->map(function (Field $field) use ($pluckKeys) {
                $properties = $field->toJson()['properties'] ?? [];
                $pluckedProperties = Arr::only($properties, $pluckKeys);
                return array_merge(['handle' => $field->handle], $pluckedProperties);
            });
        }

        return $asArray ? $fields->toArray() : $fields;
    }

    /**
     * Retrieves a field's properties as an array by its ID and row index, optionally within a specific group.
     *
     * @param string      $fieldId
     * @param integer     $rowIndex
     * @param string|null $groupId
     *
     * @return array|null
     */
    private function getSerialisedField(string $fieldId, int $rowIndex, ?string $groupId = null): ?array {
        $field = $this->getField($fieldId, $rowIndex, $groupId);

        if ($field === null) {
            return null;
        }

        return $field->toJson();
    }

    /**
     * Re-hydrates any active field instance required by the current screen.
     *
     * @return void
     */
    private function reHydrateScreen(): void {
        if ($this->isRepeaterEditorScreen()) {
            $this->resolveEditingRepeater();
        }
    }


    /**
     * Resolves the currently active field instance.
     *
     * @return Field|null
     */
    private function resolveActiveField(): ?Field {
        $resolvedField = $this->resolveActiveEntity(
            $this->activeFieldId,
            $this->activeField,
            function(string $fieldId): ?Field {
                if ($this->hasActiveRepeaterContext()) {
                    $repeater = $this->resolveEditingRepeater();

                    if ($repeater !== null) {
                        $repeaterField = $repeater->getFields()->where('id', $fieldId)->first();

                        if ($repeaterField instanceof Field) {
                            return $repeaterField;
                        }
                    }
                }

                return $this->getFieldFromRowsById($fieldId);
            }
        );

        if ($resolvedField instanceof Field) {
            $this->activeField = $resolvedField;
            return $resolvedField;
        }

        return null;
    }

    public function dumpRows(): void {
        dd($this->rows);
    }

    // =========================================================================
    // Screen Management
    // =========================================================================

    /**
     * Changes the current screen and optionally records a return target.
     *
     * @param string      $screen
     * @param string|null $returnScreen
     *
     * @return void
     */
    private function changeScreen(string $screen, ?string $returnScreen = null): void {
        if ($returnScreen !== null && trim($returnScreen) !== '') {
            $this->screenReturnTargets[$screen] = $returnScreen;
        }

        $this->screen = $screen;
    }

    /**
     * Resolves and consumes the return-screen target for a given screen.
     *
     * @param string $screen
     * @param string $default
     *
     * @return string
     */
    private function resolveReturnScreen(string $screen, string $default = 'canvas-main'): string {
        $returnScreen = $this->screenReturnTargets[$screen] ?? $default;
        unset($this->screenReturnTargets[$screen]);

        return $returnScreen;
    }

    /**
     * Resolves an active entity by ID from canonical source, with current-entity fallback.
     *
     * @param string|null $activeId
     * @param mixed       $currentEntity
     * @param callable    $canonicalResolver
     *
     * @return mixed
     */
    private function resolveActiveEntity(?string $activeId, mixed $currentEntity, callable $canonicalResolver): mixed {
        if ($activeId === null || $activeId === '') {
            return null;
        }

        $canonicalEntity = $canonicalResolver($activeId);

        if ($canonicalEntity !== null) {
            return $canonicalEntity;
        }

        if (
            $currentEntity !== null
            && method_exists($currentEntity, 'getId')
            && $currentEntity->getId() === $activeId
        ) {
            return $currentEntity;
        }

        return null;
    }

    /**
     * Returns true when the builder is currently in repeater editor mode.
     *
     * @return bool
     */
    private function isRepeaterEditorScreen(): bool {
        return $this->screen === 'canvas-repeater-editor';
    }

    /**
     * Returns true when a repeater is currently active in builder context.
     *
     * @return bool
     */
    private function hasActiveRepeaterContext(): bool {
        return $this->activeRepeaterId !== null && $this->activeRepeaterId !== '';
    }

    /**
     * Resolves a field by ID from top-level rows.
     *
     * @param string $fieldId
     *
     * @return Field|null
     */
    private function getFieldFromRowsById(string $fieldId): ?Field {
        return collect($this->rows)
            ->filter(fn($row) => $row instanceof FormRow)
            ->map(fn(FormRow $row) => $row->getField($fieldId))
            ->filter(fn($field) => $field !== null)
            ->first();
    }

    // =========================================================================
    // Rules Editor Management
    // =========================================================================

    public function openRulesEditor(): void {
        $activeField = $this->resolveActiveField();

        if ($activeField === null) {
            return;
        }

        $this->changeScreen('canvas-rules-editor', $this->screen);
        $this->dispatch('mforms:hide-field-settings');
    }

    /**
     * Closes the rules editor screen.
     *
     * @return void
     */
    public function closeRulesEditor(): void {
        $returnScreen = $this->resolveReturnScreen('canvas-rules-editor', 'canvas-main');

        if ($returnScreen === 'canvas-repeater-editor' && $this->activeRepeaterId) {
            $this->changeScreen('canvas-repeater-editor');
            $this->resolveEditingRepeater();
            $this->dispatchRepeaterEditorUpdated();
        } else {
            $this->changeScreen('canvas-main');
        }
        $this->dispatch('mforms:unhide-field-settings');
    }

    // =========================================================================
    // Choice / Options Editor Management
    // =========================================================================

    /**
     * Opens the options editor for the currently active field, if it supports options.
     *
     * @return void
     */
    public function openOptionsEditor(): void {
        $activeField = $this->resolveActiveField();

        if ($activeField === null) {
            return;
        }

        $this->changeScreen('canvas-options-editor', $this->screen);
        $this->dispatch('mforms:hide-field-settings');
    }

    /**
     * Gets the HTML for the options repeater field for the currently active choice field.
     *
     * @return string
     */
    #[Renderless]
    public function getOptionsRepeaterFieldHtml(): string {
        if ($this->activeField === null) {
            return '';
        }

        $currentOptions = $this->activeField->getOptions();
        $repeaterValue = [];

        foreach ($currentOptions as $value => $label) {
            $repeaterValue[] = [
                'option_value' => $value,
                'option_label' => $label,
            ];
        }

        $repeater = Fields::checkout(Framework::get())->makeFrom('repeater', [
            'id'             => 'field-options-editor',
            'name'           => 'field_options_editor',
            'label'          => 'Options',
            'allowConfigure' => false,
            'addRowText'     => 'Add Option'
        ]);

        $repeater->field('text', [
            'id'    => 'option_value',
            'name'  => 'option_value',
            'label' => 'Value',
        ]);

        $repeater->field('text', [
            'id'    => 'option_label',
            'name'  => 'option_label',
            'label' => 'Label',
        ]);

        $repeater->default($repeaterValue);
        $this->dispatch('mforms:form-canvas-updated');   
        return $repeater->html(true, ['label' => false]);
    }

    /**
     * Updates the options for the currently active field.
     *
     * @param array $options
     * @return void
     */
    public function updateFieldOptions(array $options): void {
        $activeField = $this->resolveActiveField();

        if ($activeField === null) {
            return;
        }

        $formattedOptions = [];

        foreach ($options as $option) {
            if (!isset($option['option_value']) || !isset($option['option_label'])) {
                continue;
            }

            $valueRaw = trim((string) $option['option_value']);
            $label = trim((string) $option['option_label']);
            $value = Str::snake($valueRaw !== '' ? $valueRaw : $label);

            if ($value === '') {
                continue;
            }

            if (array_key_exists($value, $formattedOptions)) {
                continue;
            }

            $formattedOptions[$value] = $label !== ''
                ? $label
                : Str::title(str_replace(['-', '_'], ' ', $value));
        }

        if (method_exists($activeField, 'setOptions')) {
            $activeField->setOptions($formattedOptions);
        }

        $this->activeField = $activeField;
        $this->syncRepeaterEditorAfterActiveFieldUpdate();
        $this->dispatchFieldSettingsUpdated();
        $this->closeOptionsEditor();
    }

    /**
     * Closes the options editor screen.
     *
     * @return void
     */
    private function closeOptionsEditor(): void {
        $returnScreen = $this->resolveReturnScreen('canvas-options-editor', 'canvas-main');

        if ($returnScreen === 'canvas-repeater-editor' && $this->activeRepeaterId) {
            $this->changeScreen('canvas-repeater-editor');
            $this->resolveEditingRepeater();
            $this->dispatchRepeaterEditorUpdated();
        } else {
            $this->changeScreen('canvas-main');
        }
        
        $this->dispatch('mforms:unhide-field-settings');
    }

    // =========================================================================
    // Repeater Fields Management
    // =========================================================================

    /**
     * Opens the repeater field editor.
     *
     * @param string      $fieldId
     * @param integer     $rowIndex
     * @param string|null $groupId
     *
     * @return void
     */
    public function openRepeaterEditor(string $fieldId, int $rowIndex, ?string $groupId = null): void {    
        $field = $this->getField($fieldId, $rowIndex, $groupId);

        if ($field === null || $field->handle !== 'repeater') {
            return;
        }

        $this->activeRepeater = $field;
        $this->activeRepeaterId = $fieldId;
        $this->repeaterEditorVersion++;
        $this->changeScreen('canvas-repeater-editor');
        $this->dispatch('mforms:close-field-settings');
    }

    public function closeRepeaterEditor(): void {
        $this->activeRepeater = null;
        $this->activeRepeaterId = null;
        $this->activeField = null;
        $this->activeFieldId = null;
        $this->repeaterEditorVersion++;

        $this->changeScreen('canvas-main');
        $this->dispatch('mforms:close-field-settings');
        $this->dispatch('mforms:form-canvas-updated');
    }

    /**
     * Moves a field within the repeater currently being edited to a new position.
     *
     * @param string  $fieldID
     * @param int     $newPosition
     *
     * @return void
     */
    private function moveRepeaterField(string $fieldID, int $newPosition): void {
        if ($this->activeRepeaterId === null || $this->activeRepeaterId === '') {
            return;
        }

        $repeater = $this->resolveEditingRepeater();

        if ($repeater === null) {
            return;
        }

        $newPosition = $newPosition < 0 ? 0 : $newPosition;

        $repeater->moveField($fieldID, $newPosition);
        $this->activeRepeater = $repeater;
        $this->dispatchRepeaterEditorUpdated();
    }

    /**
     * Adds a field to the repeater currently being edited at a specific position.
     *
     * @param string  $fieldHandle
     * @param integer $fieldPosition
     *
     * @return void
     */
    private function addRepeaterField(string $fieldHandle, int $fieldPosition): void {
        if ($this->activeRepeaterId === null || $this->activeRepeaterId === '') {
            return;
        }

        if (!in_array($fieldHandle, array_keys($this->fieldTypes))) {
            return;
        }

        $repeater = $this->resolveEditingRepeater();

        if ($repeater === null) {
            return;
        }

        $fieldPosition = $fieldPosition < 0 ? 0 : $fieldPosition;

        $repeater->field($fieldHandle, ['position' => $fieldPosition]);
        $this->activeRepeater = $repeater;
        $this->dispatchRepeaterEditorUpdated();
    }

    /**
     * Removes a field from the repeater currently being edited.
     *
     * @param string $fieldId
     *
     * @return void
     */
    private function removeRepeaterField(string $fieldId): void {
        if ($this->activeRepeaterId === null || $this->activeRepeaterId === '') {
            return;
        }

        $repeater = $this->resolveEditingRepeater();

        if ($repeater === null) {
            return;
        }

        $field = $this->getField($fieldId, 0);

        if ($field === null) {
            return;
        }

        $repeater->removeField($field);
        $this->activeRepeater = $repeater;
        $this->dispatchRepeaterEditorUpdated();
    }

    /**
     * Sets the default value of the repeater currently being edited.
     *
     * @param array $value
     *
     * @return void
     */
    public function setRepeaterDefaultValue(array $value): void {
        if ($this->activeRepeaterId === null || $this->activeRepeaterId === '') {
            return;
        }

        $repeater = $this->resolveEditingRepeater();

        if ($repeater === null) {
            return;
        }

        $repeater->default($value);
        $this->activeRepeater = $repeater;

        session()->flash('updateStatus', 'Default value updated');
        $this->dispatchRepeaterEditorUpdated();
    }

    /**
     * Dispatches repeater-editor update events used by Alpine to resync UI state.
     *
     * @return void
     */
    private function dispatchRepeaterEditorUpdated(): void {
        $this->repeaterEditorVersion++;
        $this->dispatch('mforms:repeater-field-updated');
        $this->dispatch('mforms:form-canvas-updated');
    }

    /**
     * Fetches the repeater field instance currently being edited in the repeater editor screen.
     *
     * @return Field|null
     */
    private function getEditingRepeater(?string $repeaterId = null): ?Repeater {
        $targetRepeaterId = $repeaterId ?? $this->activeRepeaterId;

        if ($targetRepeaterId === null || $targetRepeaterId === '') {
            return null;
        }

        return collect($this->rows)
            ->filter(fn($row) => $row instanceof FormRow)
            ->map(fn(FormRow $row) => $row->getField($targetRepeaterId))
            ->filter(fn($field) => $field instanceof Repeater)
            ->first();
    }

    /**
     * Resolves the repeater currently being edited, preferring canonical row state.
     *
     * @return Repeater|null
     */
    private function resolveEditingRepeater(): ?Repeater {
        $resolvedRepeater = $this->resolveActiveEntity(
            $this->activeRepeaterId,
            $this->activeRepeater,
            fn(string $repeaterId): ?Repeater => $this->getEditingRepeater($repeaterId)
        );

        if ($resolvedRepeater instanceof Repeater) {
            $this->activeRepeater = $resolvedRepeater;
            return $resolvedRepeater;
        }

        return null;
    }

    // =========================================================================
    // Conditions Management Methods
    // =========================================================================

    public function openConditionsEditor(string $fieldId, int $rowIndex, ?string $groupId = null): void {
        $this->setActiveField($fieldId, $rowIndex, $groupId);

        if ($this->activeField === null) {
            return;
        }

        $this->changeScreen('canvas-conditions-editor', $this->screen);
        $this->dispatch('mforms:hide-field-settings');
    }

    public function getConditionsRepeaterHtml(): string {
        $activeField = $this->resolveActiveField();

        if ($activeField === null) {
            return '';
        }

        $formFields = $this->getFields()
            ->filter(fn($field) => $field->getId() !== $activeField->getId() && $field->isInRepeater() === false);

        $currentConditions = $activeField->getConditions();

        $html = '';
        foreach ($currentConditions as $type => $configuration) {
            $html .= 
                '<fieldset 
                    class="nice-form-group" 
                    style="margin-bottom:1rem;padding:1rem;border:1px solid #ccc;border-radius:10px;background-color:#ffffff;box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"
                >
                    <legend>' . Str::title(str_replace('_', ' ', $type)) . '</legend>
                    <small class="description" style="margin-bottom:1.5rem;">Test test test</small>';
                    

            $logic = Fields::checkout(Framework::get())->makeFrom('select', [
                'id'    => "field-conditions-logic-{$type}",
                'name'  => "field_conditions_logic_{$type}",
                'label' => 'Logic',
                'options' => [
                    'and' => 'All Conditions Must Match',
                    'or'  => 'Any Condition Can Match'
                ],
                'default' => $configuration['logic'] ?? 'and'
            ]);

            $logic->attribute('style', 'margin-bottom: 0.5rem;');

            $html .= $logic->html();

            $repeater = Fields::checkout(Framework::get())->makeFrom('repeater', [
                'id'             => "field-conditions-editor-{$type}",
                'name'           => "field_conditions_editor_{$type}",
                'label'          => 'Conditions',
                'allowConfigure' => false,
                'addRowText'     => 'Add Condition'
            ]);

            $repeater->field('select', [
                'id'         => 'condition_field',
                'name'       => 'condition_field',
                'label'      => 'Field',
                'attributes' => [
                    'data-conditions-field-select' => 'true'
                ],
                'options' => $formFields->mapWithKeys(fn($field) => [$field->getId() => $field->getLabel()])->toArray(),
            ]);

            $repeater->field('select', [
                'id'         => 'condition_rule',
                'name'       => 'condition_rule',
                'label'      => 'Rule',
                'attributes' => [
                    'data-conditions-rule-select' => 'true'
                ],
                'options' => [
                    'equals'           => 'Equals',
                    'not_equals'       => 'Does Not Equal',
                    'contains'         => 'Contains',
                    'not_contains'     => 'Does Not Contain',
                    'greater_or_equal' => 'Greater Than or Equal To',
                    'greater_than'     => 'Greater Than',
                    'less_or_equal'    => 'Less Than or Equal To',
                    'less_than'        => 'Less Than'
                ],
            ]);

            $html .= $repeater->html();
            $html .= '</fieldset>';
        }

        return $html;
    }

    // =========================================================================
    // Settings Management Methods / Main Screens
    // =========================================================================

    /**
     * Public alias for changeScreen.
     *
     * @param string $screen
     *
     * @return void
     */
    public function setScreen(string $screen): void {
        $this->changeScreen($screen);
    }

    /**
     * Opens the form settings screen.
     *
     * @return void
     */
    public function openFormSettings(): void {
        $this->changeScreen('settings-main', $this->screen);
    }

    /**
     * Updates a specific form setting.
     *
     * @param string $settingKey
     * @param string $settingValue
     *
     * @return void
     */
    public function updateSetting(string $settingKey, string $settingValue): void {
        $this->formSettings[$settingKey] = $settingValue;

        if ($settingKey === 'title' && (!isset($this->formSettings['slug']) || empty($this->formSettings['slug']))) {
            $this->formSettings['slug'] = Str::slug($settingValue);
        }
    }

    /**
     * Builds the actions repeater used in the form builder settings -> actions tab.
     *
     * @return Field
     */
    public function getActionsRepeaterField(): Field {
        $actions = $this->schema['actions'] ?? [];
        $configuredActionHandles = $this->getConfiguredActionHandles(is_array($actions) ? $actions : []);

        $formFields = $this->getFields(true, true, ['name', 'label']);
        $formFieldOptions = [];

        foreach ($formFields as $field) {
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? $name;

            if (is_string($name) && $name !== '') {
                $formFieldOptions[$name] = is_string($label) && $label !== '' ? $label : $name;
            }
        }

        $actionOptions = [];
        $resolvedActions = [];

        $register = FormActions::checkout(Framework::get());

        foreach ($register->getRegistered() as $handle => $_) {
            $action = FormActions::checkout(Framework::get())->makeFrom($handle);

            if (!$action instanceof FormAction) {
                continue;
            }

            $isAvailable = $this->isFormActionAvailable($handle);

            if (!$isAvailable && !in_array($handle, $configuredActionHandles, true)) {
                continue;
            }

            $resolvedActions[$handle] = $action;
            $actionOptions[$handle] = $isAvailable
                ? $action->getLabel()
                : $action->getLabel() . ' (Unavailable: configure an active integration connection first)';
        }

        $repeater = Fields::checkout(Framework::get())->makeFrom('repeater', [
            'id' => 'form-builder-actions',
            'name' => 'actions',
            'label' => 'Actions',
            'helpText' => 'Define one or more actions to run after a successful form submission.',
        ]);

        $repeater->allowConfigure(true)
            ->allowAdd(true)
            ->allowRemove(true)
            ->allowReorder(true)
            ->configureRequiredFields(['action'])
            ->addRowText('Add Action')
            ->configureRowText('Configure')
            ->removeRowText('Remove Action');

        $repeater->field('select', [
            'id'      => 'action',
            'name'    => 'action',
            'label'   => 'Action',
            'options' => array_merge(['' => 'Select an action...'], $actionOptions),
        ]);

        foreach ($resolvedActions as $handle => $action) {
            $html = $action->renderConfigurationDialog($formFieldOptions, []);

            $repeater->customConfigurationDialogHtml($html, ['action', '=', $handle]);
        }

        $repeater->default(is_array($actions) ? $actions : []);

        return $repeater;
    }

    /**
     * Returns action handles currently present in the schema so unavailable actions can remain visible without data loss.
     *
     * @param array $actions
     * @return array
     */
    private function getConfiguredActionHandles(array $actions): array {
        $handles = [];

        foreach ($actions as $actionRow) {
            if (!is_array($actionRow)) {
                continue;
            }

            $handle = trim((string) ($actionRow['action'] ?? ''));

            if ($handle !== '') {
                $handles[] = $handle;
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * Determines whether a form action should be available in the action picker.
     *
     * @param string $handle
     * @return bool
     */
    private function isFormActionAvailable(string $handle): bool {
        return match ($handle) {
            'create_salesforce_contact' => $this->hasActiveIntegrationConnection('salesforce', null),
            'run_crm_sync_jobs' => $this->hasActiveIntegrationConnection(null, 'crm'),
            default => true,
        };
    }

    /**
     * Checks whether at least one active integration account exists with an active connection.
     *
     * @param string|null $integrationHandle
     * @param string|null $category
     * @return bool
     */
    private function hasActiveIntegrationConnection(?string $integrationHandle = null, ?string $category = null): bool {
        $query = IntegrationAccount::query()->where('is_active', true);

        if (is_string($integrationHandle) && $integrationHandle !== '') {
            $query->where('integration_handle', $integrationHandle);
        }

        if (is_string($category) && $category !== '') {
            $query->where('category', $category);
        }

        return $query
            ->whereHas('connections', function ($connectionQuery) {
                $connectionQuery
                    ->where('is_active', true)
                    ->where(function ($statusQuery) {
                        $statusQuery
                            ->whereNull('status')
                            ->orWhere('status', 'active')
                            ->orWhere('status', 'connected')
                            ->orWhere('status', 'token_refreshed');
                    })
                    ->where(function ($tokenQuery) {
                        $tokenQuery
                            ->whereNotNull('access_token')
                            ->orWhereNotNull('api_key');
                    })
                    ->where(function ($expiryQuery) {
                        $expiryQuery
                            ->whereNull('token_expires_at')
                            ->orWhere('token_expires_at', '>', now())
                            ->orWhereNotNull('refresh_token');
                    });
            })
            ->exists();
    }

    // =========================================================================
    // Saving
    // =========================================================================

    /**
     * Saves the form and its configuration to the database.
     *
     * @return void
     */
    public function saveForm(): void {
        if (!$this->form) {
            return;
        }

        $serializedRows = array_map(function($row) {
            if ($row instanceof FormRow) {
                return $row->toJson();
            }

            return null;
        }, $this->rows);


        $serializedSchema = [
            'actions'  => is_array($this->schema['actions'] ?? null) ? $this->schema['actions'] : [],
            'rows'     => $serializedRows
        ];
        
        $this->form->update([
            'post_title'   => $this->formSettings['title'] ?? 'Untitled Form',
            'post_name'    => Str::slug($this->formSettings['slug'] ?? $this->formSettings['title'] ?? 'untitled-form'),
            'post_content' => wp_kses_post($this->formSettings['description'] ?? ''),
            'post_status'  => $this->formSettings['status'] ?? 'draft'
        ]);

        $this->form->meta()->updateOrCreate(
            ['meta_key'   => '_meros_form_meta'],
            ['meta_value' => json_encode([
                'schema'  => $serializedSchema
            ])]
        );

        $this->dispatch('mforms:form-canvas-updated');
        session()->flash('updateStatus', 'Form successfully saved!');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a new form post and redirects to its builder page.
     *
     * @return void
     */
    private function makeNewForm(): void {
        $newFormId = wp_insert_post([
            'post_title'   => 'Untitled Form',
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'meros-form',
        ]);

        $defaultMeta = [
            'schema' => [
                'settings' => [],
                'rows' => []
            ]
        ];

        FormMeta::create([
            'post_id' => $newFormId,
            'meta_key' => '_meros_form_meta',
            'meta_value' => json_encode($defaultMeta)
        ]);

        if (!is_int($newFormId)) {
            abort(500, 'Failed to create new form.');
        }

        $this->redirect(route('meros.toolbox.form-builder.edit', ['formID' => $newFormId]));
    }

    /**
     * Returns dynamic choice source definitions available to the builder settings panel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDynamicChoiceSourcesForBuilder(): array {
        $register = DynamicChoiceSourcesAccessor::checkout(Framework::get());
        $sources = $register->allResolved();

        return $sources
            ->filter(fn ($source) => method_exists($source, 'isAvailable') ? (bool) $source->isAvailable() : true)
            ->map(fn ($source) => $source->toBuilderDefinition())
            ->filter(fn ($source) => is_array($source) && !empty($source['source']))
            ->values()
            ->toArray();
    }
}