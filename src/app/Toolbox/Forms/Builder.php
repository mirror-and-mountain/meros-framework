<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\FormActions;

use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\PostMeta as FormMeta;

use MM\Meros\App\Toolbox\Forms\Concerns\ManagesFormSchema;

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
        // dd($this->activeField->toJson()['properties'] ?? null);
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
     * @param integer     $rowIndex
     * @param string|null $groupId
     *
     * @return void
     */
    #[Renderless]
    public function updateActiveFieldProperty(string $property, mixed $value, int $rowIndex, ?string $groupId = null): void {
        if ($this->activeField === null) {
            return;
        }

        $canonicalField = $this->getField($this->activeFieldId, $rowIndex, $groupId);

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
                $this->dispatch('mforms:refresh-field-settings');
                return;
            }
        }

        if ($property === 'rule' && is_array($value)) {
            $rule = $value['rule'] ?? null;
            $value = $value['value'] ?? null;

            if ($rule !== null) {
                $canonicalField->rule($rule, $value);
                $this->activeField = $canonicalField;
                $this->dispatch('mforms:refresh-field-settings');
                return;
            }
        }

        if ($property === 'rules' && is_array($value)) {
            $canonicalField->rules(Arr::keyBy($value, 'rule'));
            
            $this->activeField = $canonicalField;
            $this->dispatch('mforms:refresh-field-settings');
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
        $this->dispatch('mforms:refresh-field-settings');
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
        if ($this->screen === 'canvas-repeater-editor') {
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
        if ($this->screen === 'canvas-repeater-editor') {
            $this->resolveEditingRepeater();
        }
    }

    public function dumpRows(): void {
        dd($this->rows);
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
        $this->screen = 'canvas-repeater-editor';
        $this->dispatch('mforms:close-field-settings');
    }

    public function closeRepeaterEditor(): void {
        $this->activeRepeater = null;
        $this->activeRepeaterId = null;
        $this->activeField = null;
        $this->activeFieldId = null;

        $this->screen = 'canvas-main';
        $this->dispatch('mforms:close-field-settings');
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

        $repeater->removeField($field);
        $this->activeRepeater = $repeater;
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
        $this->dispatch('mforms:form-canvas-updated');
    }

    /**
     * Fetches the repeater field instance currently being edited in the repeater editor screen.
     *
     * @return Field|null
     */
    private function getEditingRepeater(): ?Repeater {
        return collect($this->rows)
            ->filter(fn($row) => $row instanceof FormRow)
            ->map(fn(FormRow $row) => $row->getField($this->activeRepeaterId))
            ->filter(fn($field) => $field instanceof Repeater)
            ->first();
    }

    /**
     * Resolves the repeater currently being edited, preferring canonical row state.
     *
     * @return Repeater|null
     */
    private function resolveEditingRepeater(): ?Repeater {
        if ($this->activeRepeaterId === null || $this->activeRepeaterId === '') {
            return null;
        }

        $canonicalRepeater = $this->getEditingRepeater();

        if ($canonicalRepeater !== null) {
            $this->activeRepeater = $canonicalRepeater;
            return $canonicalRepeater;
        }

        if (
            $this->activeRepeater !== null
            && $this->activeRepeater->getId() === $this->activeRepeaterId
        ) {
            return $this->activeRepeater;
        }

        return null;
    }

    // =========================================================================
    // Conditions Management Methods
    // =========================================================================

    /**
     * Builds and returns repeater field instances for managing the conditions of the field currently being edited.
     *
     * @return array
     */
    public function getFieldConditionsRepeaters(): array {
        if ($this->editingField === null) {
            return [];
        }

        $conditions = $this->editingField->getConditions() ?? [];
        $currentFields = $this->getCurrentFields();

        $currentFieldLabels = $currentFields->pluck('label', 'id')->toArray();
        $currentFieldHandlesById = $currentFields->pluck('type', 'id')->toArray();

        $repeaters = [];
        foreach ($conditions as $type => $configuration) {
            $rules = $configuration['rules'] ?? [];
            $operatorOptions = $this->getFieldConditionOperatorOptionsForRules($rules, $currentFieldHandlesById);
            $rulesForRepeaterDefault = $this->normaliseFieldConditionRulesForRepeaterDefault($rules);

            $repeater = Fields::checkout(Framework::get())->makeFrom('repeater', [
                'id'             => 'field-conditions-' . $type,
                'name'           => 'field_conditions_' . $type,
                'allowConfigure' => false,
                'onAddRow'       => '$store.formBuilder.handleFieldConditionsRepeaterAddRow',
                'onRemoveRow'    => '$store.formBuilder.syncFieldConditionsRepeaterSelectionState',
                'onMoveRow'      => '$store.formBuilder.syncFieldConditionsRepeaterSelectionState',
                'addRowText'     => 'Add Condition',
                'placeholder'    => 'No conditions added yet.'
            ]);

            $repeater->class('meros-field-conditions-repeater');

            $repeater->subField('select')
                ->name('field_id')
                ->label('Field Name')
                ->options(array_merge(['' => 'Select a field'], $currentFieldLabels))
                ->onChange('$store.formBuilder.setFieldConditionsRow');

            $repeater->subField('select')
                ->name('operator')
                ->label('Operator')
                ->options($operatorOptions)
                ->onChange('$store.formBuilder.setFieldConditionOperatorRow');

            $repeater->subField('text')
                ->name('value')
                ->label('Value')
                ->disabled();

            $repeater->default($rulesForRepeaterDefault);

            $repeaters[$type] = $repeater;
        }

        return $repeaters;
    }

    /**
     * Normalises condition rules so repeater default hydration remains safe for text fallback fields.
     *
     * @param array $rules
     *
     * @return array
     */
    private function normaliseFieldConditionRulesForRepeaterDefault(array $rules): array {
        return array_map(function($rule) {
            if (!is_array($rule)) {
                return $rule;
            }

            if (!array_key_exists('value', $rule)) {
                return $rule;
            }

            $value = $rule['value'];

            if (is_array($value) || is_object($value)) {
                $rule['value'] = json_encode($value);
            }

            return $rule;
        }, $rules);
    }

    /**
     * Builds field-condition operator options for a ruleset by resolving each rule's field handle.
     *
     * @param array $rules
     * @param array $fieldHandlesById
     *
     * @return array
     */
    private function getFieldConditionOperatorOptionsForRules(array $rules, array $fieldHandlesById): array {
        if (empty($rules)) {
            return [];
        }

        $allowedOperators = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $fieldId = (string) ($rule['field_id'] ?? '');
            $fieldType = $fieldHandlesById[$fieldId] ?? null;

            foreach ($this->getFieldConditionOperatorsForFieldType($fieldType) as $operator) {
                if (!in_array($operator, $allowedOperators, true)) {
                    $allowedOperators[] = $operator;
                }
            }

            $savedOperator = (string) ($rule['operator'] ?? '');

            if ($savedOperator !== '' && !in_array($savedOperator, $allowedOperators, true)) {
                $allowedOperators[] = $savedOperator;
            }
        }

        if (empty($allowedOperators)) {
            return [];
        }

        $options = ['' => 'Select operator'];

        foreach ($allowedOperators as $operator) {
            $options[$operator] = $this->formatFieldConditionOperatorLabel($operator);
        }

        return $options;
    }

    /**
     * Returns available field-condition operators for a field type.
     * Mirrors the operator map in formBuilderStore.
     *
     * @param string|null $fieldType
     *
     * @return array
     */
    private function getFieldConditionOperatorsForFieldType(?string $fieldType): array {
        $operatorMap = $this->getFieldConditionOperatorMap();

        if ($fieldType === null || $fieldType === '') {
            return [];
        }

        return $operatorMap[$fieldType] ?? ['equals', 'not_equals', 'is_empty', 'is_not_empty'];
    }

    /**
     * Returns the canonical field-condition operator map.
     *
     * @return array
     */
    public function getFieldConditionOperatorMap(): array {
        return [
            'text'            => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'textarea'        => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'rich_text'       => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'email'           => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'url'             => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'tel'             => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'hidden'          => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'number'          => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal_to', 'less_than_or_equal_to', 'is_empty', 'is_not_empty'],
            'range'           => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal_to', 'less_than_or_equal_to', 'is_empty', 'is_not_empty'],
            'select'          => ['equals', 'not_equals'],
            'advanced_select' => ['equals', 'not_equals'],
            'radio'           => ['equals', 'not_equals'],
            'multi_select'    => ['contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'checkboxes'      => ['contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'date'            => ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'is_empty', 'is_not_empty'],
            'time'            => ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'is_empty', 'is_not_empty'],
            'checkbox'        => ['is_checked', 'is_unchecked'],
            'repeater'        => ['is_empty', 'is_not_empty']
        ];
    }

    /**
     * Formats an operator key for select-option labels.
     *
     * @param string $operator
     *
     * @return string
     */
    private function formatFieldConditionOperatorLabel(string $operator): string {
        return Str::title(str_replace('_', ' ', $operator));
    }

    // =========================================================================
    // Saving and Default Schema
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
            'actions'  => [],
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
}