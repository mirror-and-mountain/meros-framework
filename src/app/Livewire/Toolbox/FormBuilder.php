<?php

namespace MM\Meros\App\Livewire\Toolbox;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use Livewire\Component;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\FieldGroup;
use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;
use MM\Meros\Facades\Framework;

class FormBuilder extends Component {

    /**
     * Field classes available for the form builder.
     *
     * @var array
     */
    public array $fieldTypes = [];

    /**
     * Field group classes available for the form builder.
     *
     * @var array
     */
    public array $fieldGroups = [];

    /**
     * The field categories for organising field types in the UI.
     *
     * @var array
     */
    public array $fieldCategories = [];

    /**
     * Instantiated form elements
     *
     * @var Collection<Field|FieldGroup>
     */
    private Collection $elements;

    /**
     * The rows of the form, each row containing up to 3 field definitions.
     *
     * @var array
     */
    public array $rows = [];

    /**
    * Legacy field group container payloads (deprecated).
     *
     * @var array
     */
    public array $groups = [];

    /**
     * The currently selected repeater field location.
     *
     * @var array<string, int|null>|null
     */
    public ?array $activeRepeater = null;

    /**
     * The currently selected repeater row index for editing.
     *
     * @var int|null
     */
    public ?int $activeRepeaterRow = null;

    /**
     * The currently selected non-repeater field location for settings.
     *
     * @var array<string, int|null>|null
     */
    public ?array $activeFieldSettings = null;

    public function mount(): void {
        $this->elements = collect([]);

        foreach (Fields::getRegistered() as $handle => $fieldType) {
            $this->fieldTypes[$handle] = $fieldType;
            $category                  = $fieldType::getCategory();

            $this->fieldCategories[$category][$handle] = [
                'handle' => $handle,
                'class'  => $fieldType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
                'icon'   => $fieldType::getIcon(),
            ];
        }

        foreach (FieldGroupsRegister::getRegistered() as $handle => $groupType) {
            $this->fieldGroups[$handle] = [
                'handle' => $handle,
                'class'  => $groupType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
            ];
        }
    }

    public function render() {
        // Validate that activeFieldSettings still points to a valid field
        $this->validateActiveFieldSettings();

        $formRows = $this->getHydratedRows();
        $formGroups = [];
        $canvasRows = [];
        $fieldVersions = [];  // Map field IDs to their version numbers

        foreach ($this->rows as $rowIndex => $rowPayload) {
            if ($this->isGroupRow($rowPayload)) {
                $groupPayload = $rowPayload['group'] ?? [];
                $groupRows = $this->hydratePayloadRows($groupPayload['rows'] ?? []);
                $groupObject = $this->makeFieldGroupFromPayload($groupPayload);
                $formGroups[] = [
                    'rowIndex'    => $rowIndex,
                    'id'          => $groupPayload['id'] ?? Str::uuid()->toString(),
                    'handle'      => $groupPayload['handle'] ?? '',
                    'title'       => $groupPayload['title'] ?? 'Untitled Section',
                    'description' => $groupPayload['description'] ?? '',
                    'rows'        => $groupRows,
                ];

                // Collect field versions from group
                foreach (($groupPayload['rows'] ?? []) as $groupRowPayload) {
                    foreach ((array) $groupRowPayload as $fieldPayload) {
                        if (is_array($fieldPayload) && isset($fieldPayload['id'])) {
                            $fieldVersions[$fieldPayload['id']] = $fieldPayload['_fieldVersion'] ?? 0;
                        }
                    }
                }

                $canvasRows[] = [
                    '_type' => 'group',
                    'rowIndex' => $rowIndex,
                    'group' => [
                        'id' => $groupPayload['id'] ?? Str::uuid()->toString(),
                        'object' => $groupObject,
                        'title' => $groupPayload['title'] ?? 'Untitled Section',
                        'description' => $groupPayload['description'] ?? '',
                        'rows' => $groupRows,
                    ],
                ];

                continue;
            }

            // Collect field versions from top-level rows
            foreach ((array) $rowPayload as $fieldPayload) {
                if (is_array($fieldPayload) && isset($fieldPayload['id'])) {
                    $fieldVersions[$fieldPayload['id']] = $fieldPayload['_fieldVersion'] ?? 0;
                }
            }

            $hydratedFields = $this->hydratePayloadRows([$rowPayload]);
            $canvasRows[] = [
                '_type' => 'fields',
                'rowIndex' => $rowIndex,
                'fields' => $hydratedFields[0] ?? [],
            ];
        }

        return view('meros::livewire.toolbox.form-builder.index', [
            'fieldCategories' => $this->fieldCategories, // For sidebar listing
            'fieldGroups'     => $this->fieldGroups,
            'formRows'        => $formRows, // For canvas rendering
            'formGroups'      => $formGroups,
            'canvasRows'      => $canvasRows,
            'fieldVersions'   => $fieldVersions, // Map field ID => version
            'activeRepeaterField' => $this->getActiveRepeaterViewModel(),
            'activeRepeater' => $this->activeRepeater,
            'activeRepeaterRow' => $this->activeRepeaterRow,
            'activeFieldSettingsModel' => $this->getActiveFieldSettingsViewModel(),
        ])
            ->layout('meros::livewire.toolbox.layout', ['title' => 'Form Builder']);
    }

    /***************************
     * Actions
     ***************************/
    /**
     * Adds a new field to a new row inserted after $afterRowIndex. If $afterRowIndex is -1, adds to the beginning.
     *
     * @param integer $afterRowIndex
     * @param string  $handle
     *
     * @return void
     */
    public function addFieldToNewRow(int $afterRowIndex, string $handle): void {
        $payload = $this->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;
        $newRowIndex = max(0, min($afterRowIndex + 1, count($rows)));
        $this->insertRowAfter($rows, $afterRowIndex, [$payload]);
        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => null,
            'rowIndex' => $newRowIndex,
            'fieldIndex' => 0,
        ]);
    }

    /**
     * Adds a field group container to the canvas.
     * If $handle is empty, a blank group is created.
     * 
     * @param string $handle The handle of the field group to add, or empty for a blank group.
     * 
     * @return void
     */
    public function addGroupToCanvas(string $handle = ''): void {
        $payload = $this->makeFieldGroupPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;
        $rows[] = [
            '_type' => 'group',
            'group' => $payload,
        ];
        $this->rows = $rows;
    }

    /**
     * Inserts a field group container before any existing top-level row index.
     *
     * @param integer $beforeRowIndex The top-level row index to insert before.
     * @param string  $handle The handle of the field group to add, or empty for a blank group.
     *
     * @return void
     */
    public function addGroupBeforeRow(int $beforeRowIndex, string $handle = ''): void {
        $payload = $this->makeFieldGroupPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;
        $insertAt = max(0, min($beforeRowIndex, count($rows)));

        array_splice($rows, $insertAt, 0, [[
            '_type' => 'group',
            'group' => $payload,
        ]]);

        $this->rows = $rows;
    }

    /**
     * Adds a field to a new row inside a group container.
     * 
     * @param integer $groupRowIndex The index of the group row to add the field to.
     * @param integer $afterRowIndex The index of the row inside the group to add the new row after. If -1, adds to the beginning of the group.
     * @param string  $handle The handle of the field type to add.
     * 
     * @return void
     */
    public function addFieldToGroupNewRow(int $groupRowIndex, int $afterRowIndex, string $handle): void {
        $payload = $this->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows = $rows[$groupRowIndex]['group']['rows'] ?? [];
        $newRowIndex = max(0, min($afterRowIndex + 1, count($groupRows)));
        $this->insertRowAfter($groupRows, $afterRowIndex, [$payload]);
        $rows[$groupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $newRowIndex,
            'fieldIndex' => 0,
        ]);
    }

    /**
     * Inserts a field into an existing row inside a group (max 3 fields per row).
     * 
     * @param integer $groupRowIndex The index of the group row to add the field to.
     * @param integer $rowIndex The index of the row inside the group to insert the field into.
     * @param integer $position The position within the row to insert the field (0-based).
     * @param string  $handle The handle of the field type to add.
     *
     * @return void
     */
    public function insertFieldIntoGroupRow(int $groupRowIndex, int $rowIndex, int $position, string $handle): void {
        $payload = $this->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $targetPosition = max(0, min($position, count($rows[$groupRowIndex]['group']['rows'][$rowIndex] ?? [])));

        if (!$this->insertFieldIntoPayloadRow($rows[$groupRowIndex]['group']['rows'], $rowIndex, $position, $payload)) {
            return;
        }

        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $targetPosition,
        ]);
    }

    /**
     * Removes a group from the canvas.
     * 
     * @param integer $groupRowIndex The index of the group row to remove.
     * 
     * @return void
     */
    public function removeGroup(int $groupRowIndex): void {
        if (!isset($this->rows[$groupRowIndex]) || !$this->isGroupRow($this->rows[$groupRowIndex])) {
            return;
        }

        $rows = $this->rows;
        array_splice($rows, $groupRowIndex, 1);
        $this->rows = $rows;
    }

    /**
     * Move a group row before another row index.
     * 
     * @param integer $fromGroupRowIndex The index of the group row to move.
     * @param integer $beforeRowIndex The index of the row to move before.
     * 
     * @return void
     */
    public function moveGroupRowBefore(int $fromGroupRowIndex, int $beforeRowIndex): void {
        if (!isset($this->rows[$fromGroupRowIndex]) || !$this->isGroupRow($this->rows[$fromGroupRowIndex])) {
            return;
        }

        $targetIndex = max(0, min($beforeRowIndex, count($this->rows)));

        if ($fromGroupRowIndex === $targetIndex || $fromGroupRowIndex + 1 === $targetIndex) {
            return;
        }

        $rows = $this->rows;
        [$groupRow] = array_splice($rows, $fromGroupRowIndex, 1);

        $insertAt = $targetIndex > $fromGroupRowIndex ? $targetIndex - 1 : $targetIndex;
        array_splice($rows, $insertAt, 0, [$groupRow]);

        $this->rows = $rows;
    }

    /**
     * Move a group row to the end of the top-level rows list.
     * 
     * @param integer $fromGroupRowIndex The index of the group row to move.
     * 
     * @return void
     */
    public function moveGroupRowToEnd(int $fromGroupRowIndex): void {
        if (!isset($this->rows[$fromGroupRowIndex]) || !$this->isGroupRow($this->rows[$fromGroupRowIndex])) {
            return;
        }

        $rows = $this->rows;
        [$groupRow] = array_splice($rows, $fromGroupRowIndex, 1);
        $rows[] = $groupRow;

        $this->rows = $rows;
    }

    /**
     * Open the settings panel for a top-level field.
     */
    public function editField(int $rowIndex, int $fieldIndex): void {
        $this->selectFieldSettings([
            'groupRowIndex' => null,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ]);
    }

    /**
     * Open the settings panel for a grouped field.
     */
    public function editGroupField(int $groupRowIndex, int $rowIndex, int $fieldIndex): void {
        $this->selectFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ]);
    }

    /**
     * Close the generic field settings panel.
     */
    public function closeFieldSettings(): void {
        $this->activeFieldSettings = null;
    }

    /**
     * Update the default value of a canvas field at the given location.
     * Called when the user interacts with a field directly on the canvas.
     */
    public function updateFieldDefaultValue(?int $groupRowIndex, ?int $rowIndex, ?int $fieldIndex, mixed $value): void {
        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return;
        }

        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($value): array {
            $payload['value'] = $value;
            return $payload;
        });
    }

    /**
     * Update a setting on the active field payload.
     */
    public function updateActiveFieldSetting(string $key, mixed $value): void {
        if (!$this->activeFieldSettings) {
            return;
        }

        $updated = $this->mutateFieldPayloadAt($this->activeFieldSettings, function (array $payload) use ($key, $value): array {
            if (($payload['handle'] ?? null) === 'repeater') {
                return $payload;
            }

            if ($key === 'optionsText') {
                $payload['options'] = $this->parseOptionsText((string) $value);
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if (in_array($key, ['required', 'disabled'], true)) {
                $payload[$key] = (bool) $value;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'rows') {
                $payload['rows'] = max(1, (int) $value);
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'id') {
                $nextId = trim((string) $value);

                if ($nextId !== '') {
                    $payload['id'] = $nextId;
                }

                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'name') {
                $nextName = trim((string) $value);
                $payload['name'] = $nextName === '' ? (string) ($payload['handle'] ?? 'field') : $nextName;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if (in_array($key, ['label', 'helpText', 'helpTextPosition', 'width', 'placeholder', 'advanced', 'allowAdd'], true)) {
                $payload[$key] = is_string($value) ? $value : (string) $value;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            return $payload;
        });

        if ($updated) {
            $this->dispatchTomSelectFieldSyncForLocation($this->activeFieldSettings);
        }
    }

    /**
     * Close the repeater builder panel.
     */
    public function closeRepeaterBuilder(): void {
        $this->activeRepeater = null;
        $this->activeRepeaterRow = null;
    }

    /**
     * Select a row for editing in the repeater builder.
     */
    public function selectRepeaterRow(int $rowIndex): void {
        $this->activeRepeaterRow = $rowIndex;
    }

    /**
     * Close the repeater row editor.
     */
    public function closeRepeaterRow(): void {
        $this->activeRepeaterRow = null;
    }

    /**
     * Move a default row in the active repeater preview.
     */
    public function moveRepeaterDefaultRow(int $fromIndex, int $toIndex): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($fromIndex, $toIndex): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$fromIndex]) || $fromIndex === $toIndex) {
                return $repeaterPayload;
            }

            // Extract the row being moved
            $movingRow = $rows[$fromIndex];
            
            // Remove from old position
            unset($rows[$fromIndex]);
            $rows = array_values($rows);
            
            // Insert at new position
            array_splice($rows, $toIndex, 0, [$movingRow]);

            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value'] = $rows;
            return $repeaterPayload;
        });
    }

    /**
     * Insert a column into the active repeater.
     */
    public function addRepeaterColumnAt(int $position, string $handle): void {
        $payload = $this->makeFieldPayload($handle);

        if (!$payload || $handle === 'repeater') {
            return;
        }

        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($payload, $position): array {
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $insertAt = max(0, min($position, count($columns)));

            array_splice($columns, $insertAt, 0, [$payload]);

            $repeaterPayload['fields'] = $columns;

            return $repeaterPayload;
        });
    }

    /**
     * Remove a column from the active repeater.
     */
    public function removeRepeaterColumn(int $columnIndex): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($columnIndex): array {
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));

            if (!isset($columns[$columnIndex])) {
                return $repeaterPayload;
            }

            array_splice($columns, $columnIndex, 1);
            $repeaterPayload['fields'] = $columns;

            return $repeaterPayload;
        });
    }

    /**
     * Reorder a column inside the active repeater.
     */
    public function moveRepeaterColumn(int $fromIndex, int $toIndex): void {
        if ($fromIndex === $toIndex || $fromIndex + 1 === $toIndex) {
            return;
        }

        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($fromIndex, $toIndex): array {
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));

            if (!isset($columns[$fromIndex])) {
                return $repeaterPayload;
            }

            [$column] = array_splice($columns, $fromIndex, 1);
            $insertAt = $toIndex > $fromIndex ? $toIndex - 1 : $toIndex;
            $insertAt = max(0, min($insertAt, count($columns)));

            array_splice($columns, $insertAt, 0, [$column]);
            $repeaterPayload['fields'] = $columns;

            return $repeaterPayload;
        });
    }

    /**
     * Update a repeater column's settings.
     */
    public function updateRepeaterColumnSetting(int $columnIndex, string $key, mixed $value): void {
        $shouldDispatchSync = false;

        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($columnIndex, $key, $value, &$shouldDispatchSync): array {
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));

            if (!isset($columns[$columnIndex])) {
                return $repeaterPayload;
            }

            $column = $columns[$columnIndex];
            $oldName = (string) ($column['name'] ?? ($column['handle'] ?? ''));

            if ($key === 'optionsText') {
                $column['options'] = $this->parseOptionsText((string) $value);
                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;
                $shouldDispatchSync = true;
                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
                return $repeaterPayload;
            }

            if (in_array($key, ['required', 'disabled'], true)) {
                $column[$key] = (bool) $value;
                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;
                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
                return $repeaterPayload;
            }

            if ($key === 'rows') {
                $column['rows'] = max(1, (int) $value);
                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;
                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
                return $repeaterPayload;
            }

            if ($key === 'id') {
                $nextId = trim((string) $value);

                if ($nextId !== '') {
                    $column['id'] = $nextId;
                }

                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;
                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
                return $repeaterPayload;
            }

            if ($key === 'name') {
                $newName = trim((string) $value);

                if ($newName === '') {
                    $newName = (string) ($column['handle'] ?? 'field');
                }

                $column['name'] = $newName;
                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;

                if ($oldName !== '' && $newName !== '' && $oldName !== $newName) {
                    $defaultRows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

                    foreach ($defaultRows as $rowIdx => $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        if (array_key_exists($oldName, $row) && !array_key_exists($newName, $row)) {
                            $row[$newName] = $row[$oldName];
                        }

                        unset($row[$oldName]);
                        $defaultRows[$rowIdx] = $row;
                    }

                    $repeaterPayload['defaultRows'] = $defaultRows;
                    $repeaterPayload['value'] = $defaultRows;
                }

                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
                return $repeaterPayload;
            }

            if (in_array($key, ['label', 'helpText', 'helpTextPosition', 'width', 'placeholder', 'advanced', 'allowAdd'], true)) {
                $column[$key] = is_string($value) ? $value : (string) $value;
                $columns[$columnIndex] = $column;
                $repeaterPayload['fields'] = $columns;
                $shouldDispatchSync = in_array($key, ['advanced', 'allowAdd'], true);
                $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
            }

            // Increment version to force Livewire to update the component
            $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;

            return $repeaterPayload;
        });

        // Dispatch sync event for select-type columns when relevant settings change
        if ($shouldDispatchSync && $this->activeRepeater) {
            $this->dispatchTomSelectRepeaterColumnSyncForLocation($this->activeRepeater, $columnIndex);
        }
    }

    /**
     * Add a default row and open its settings panel for editing.
     */
    public function addRepeaterRowAndEdit(): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $newRow = [];

            foreach ($columns as $columnPayload) {
                $columnName = $columnPayload['name'] ?? $columnPayload['handle'] ?? null;

                if (is_string($columnName) && $columnName !== '') {
                    $newRow[$columnName] = null;
                }
            }

            $rows[] = $newRow;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value'] = $rows;

            // Set the active row to the newly added row
            $this->activeRepeaterRow = count($rows) - 1;

            return $repeaterPayload;
        });
    }

    /**
     * Append a default row to the active repeater preview.
     */
    public function addRepeaterDefaultRow(): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $newRow = [];

            foreach ($columns as $columnPayload) {
                $columnName = $columnPayload['name'] ?? $columnPayload['handle'] ?? null;

                if (is_string($columnName) && $columnName !== '') {
                    $newRow[$columnName] = null;
                }
            }

            $rows[] = $newRow;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value'] = $rows;

            return $repeaterPayload;
        });
    }

    /**
     * Remove a default row from the active repeater preview.
     */
    public function removeRepeaterDefaultRow(int $rowIndex): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($rowIndex): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$rowIndex])) {
                return $repeaterPayload;
            }

            array_splice($rows, $rowIndex, 1);
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value'] = $rows;

            return $repeaterPayload;
        });
    }

    /**
     * Update a single default row value in the active repeater preview.
     */
    public function updateRepeaterDefaultRowValue(int $rowIndex, string $fieldName, mixed $value): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($rowIndex, $fieldName, $value): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$rowIndex])) {
                return $repeaterPayload;
            }

            $rows[$rowIndex][$fieldName] = $value;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value'] = $rows;

            return $repeaterPayload;
        });

        // Dispatch value sync event for TomSelect fields in this row
        if ($this->activeRepeater) {
            $this->dispatchTomSelectRepeaterRowValueSyncForLocation($this->activeRepeater, $rowIndex, $fieldName, $value);
        }
    }

    /**
     * Update a single row value in a canvas repeater field.
     */
    public function updateFieldRepeaterRowValue(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex, int $repeaterRowIndex, string $fieldName, mixed $value): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($repeaterRowIndex, $fieldName, $value): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$repeaterRowIndex])) {
                return $payload;
            }

            $rows[$repeaterRowIndex][$fieldName] = $value;
            $payload['defaultRows'] = $rows;
            $payload['value'] = $rows;

            return $payload;
        });

        // Dispatch value sync event for TomSelect fields in this row
        $this->dispatchTomSelectRepeaterRowValueSyncForLocation($location, $repeaterRowIndex, $fieldName, $value);
    }

    /**
     * Add a row to a field's repeater in the canvas.
     */
    public function addFieldRepeaterRow(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex = null): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($payload['fields'] ?? [], 'is_array'));

            if (empty($columns)) {
                $defaultTextColumn = $this->makeFieldPayload('text');

                if (is_array($defaultTextColumn)) {
                    $columns[] = $defaultTextColumn;
                    $payload['fields'] = $columns;
                }
            }

            $newRow = [];
            foreach ($columns as $columnPayload) {
                $columnName = $columnPayload['name'] ?? $columnPayload['handle'] ?? null;

                if (is_string($columnName) && $columnName !== '') {
                    $newRow[$columnName] = null;
                }
            }

            $rows[] = $newRow;
            $payload['defaultRows'] = $rows;
            $payload['value'] = $rows;

            return $payload;
        });
    }

    /**
     * Remove a row from a field's repeater in the canvas.
     */
    public function removeFieldRepeaterRow(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex, int $removeRowIndex): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($removeRowIndex): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));

            if (isset($rows[$removeRowIndex])) {
                unset($rows[$removeRowIndex]);
                $rows = array_values($rows);
                $payload['defaultRows'] = $rows;
                $payload['value'] = $rows;
            }

            return $payload;
        });
    }

    /**
     * Reorder rows in a field's repeater in the canvas.
     */
    public function moveFieldRepeaterRow(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex, int $fromIndex, int $toIndex): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($fromIndex, $toIndex): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$fromIndex]) || $fromIndex === $toIndex) {
                return $payload;
            }

            // Extract the row being moved
            $movingRow = $rows[$fromIndex];
            
            // Remove from old position
            unset($rows[$fromIndex]);
            $rows = array_values($rows);
            
            // Insert at new position
            array_splice($rows, $toIndex, 0, [$movingRow]);

            $payload['defaultRows'] = $rows;
            $payload['value'] = $rows;

            return $payload;
        });
    }

    /**
     * Remove a field from a group row and remove empty rows.
     * 
     * @param integer $groupRowIndex The index of the group row to remove the field from.
     * @param integer $rowIndex The index of the row inside the group to remove the field from.
     * @param integer $fieldIndex The index of the field within the row to remove.
     * 
     * @return void
     */
    public function removeFieldFromGroup(int $groupRowIndex, int $rowIndex, int $fieldIndex): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        if (!$this->removeFieldFromPayloadRow($rows[$groupRowIndex]['group']['rows'], $rowIndex, $fieldIndex)) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Relocate an existing field within a group's rows.
     */
    public function relocateFieldInGroup(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows = &$rows[$groupRowIndex]['group']['rows'];

        if (!isset($groupRows[$fromRowIndex][$fromFieldIndex])) {
            return;
        }

        if ($fromRowIndex === $toRowIndex) {
            if ($fromFieldIndex === $toPosition || $fromFieldIndex + 1 === $toPosition) {
                return;
            }

            $row = $groupRows[$fromRowIndex];
            [$field] = array_splice($row, $fromFieldIndex, 1);
            $insertAt = $toPosition > $fromFieldIndex ? $toPosition - 1 : $toPosition;
            array_splice($row, $insertAt, 0, [$field]);
            $groupRows[$fromRowIndex] = $row;
            $this->rows = $rows;
            return;
        }

        if (!isset($groupRows[$toRowIndex]) || count($groupRows[$toRowIndex]) >= 3) {
            return;
        }

        [$field] = array_splice($groupRows[$fromRowIndex], $fromFieldIndex, 1);

        if (empty($groupRows[$fromRowIndex])) {
            array_splice($groupRows, $fromRowIndex, 1);

            if ($toRowIndex > $fromRowIndex) {
                $toRowIndex--;
            }
        }

        array_splice($groupRows[$toRowIndex], $toPosition, 0, [$field]);
        $this->rows = $rows;
    }

    /**
     * Move an existing group field into a new row inside the same group.
     */
    public function moveFieldToGroupNewRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $afterRowIndex): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows = &$rows[$groupRowIndex]['group']['rows'];

        if (!isset($groupRows[$fromRowIndex][$fromFieldIndex])) {
            return;
        }

        [$field] = array_splice($groupRows[$fromRowIndex], $fromFieldIndex, 1);

        if (empty($groupRows[$fromRowIndex])) {
            array_splice($groupRows, $fromRowIndex, 1);

            if ($afterRowIndex >= $fromRowIndex) {
                $afterRowIndex--;
            }
        }

        $this->insertRowAfter($groupRows, $afterRowIndex, [$field]);
        $this->rows = $rows;
    }

    /**
     * Move an existing top-level field into a row inside a group.
     */
    public function moveFieldToGroupRow(int $fromRowIndex, int $fromFieldIndex, int $groupRowIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;
        $result = $this->extractFieldFromPayloadRows($rows, $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if ($result['rowRemoved'] && $fromRowIndex < $groupRowIndex) {
            $groupRowIndex--;
        }

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        if (!$this->insertFieldIntoPayloadRow($rows[$groupRowIndex]['group']['rows'], $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing top-level field into a new row inside a group.
     */
    public function moveFieldToGroupNewRowFromTopLevel(int $fromRowIndex, int $fromFieldIndex, int $groupRowIndex, int $afterRowIndex): void {
        $rows = $this->rows;
        $result = $this->extractFieldFromPayloadRows($rows, $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if ($result['rowRemoved'] && $fromRowIndex < $groupRowIndex) {
            $groupRowIndex--;
        }

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows = $rows[$groupRowIndex]['group']['rows'] ?? [];
        $this->insertRowAfter($groupRows, $afterRowIndex, [$result['field']]);
        $rows[$groupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a top-level row.
     */
    public function moveFieldFromGroupToRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $result = $this->extractFieldFromPayloadRows($rows[$groupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if (!$this->insertFieldIntoPayloadRow($rows, $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a new top-level row.
     */
    public function moveFieldFromGroupToNewRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $afterRowIndex): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $result = $this->extractFieldFromPayloadRows($rows[$groupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        $this->insertRowAfter($rows, $afterRowIndex, [$result['field']]);
        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a row in another group.
     */
    public function moveFieldBetweenGroups(int $fromGroupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toGroupRowIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $fromGroupRowIndex) || !$this->isValidGroupRowIndex($rows, $toGroupRowIndex)) {
            return;
        }

        $result = $this->extractFieldFromPayloadRows($rows[$fromGroupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if (!$this->insertFieldIntoPayloadRow($rows[$toGroupRowIndex]['group']['rows'], $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a new row in another group.
     */
    public function moveFieldBetweenGroupsToNewRow(int $fromGroupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toGroupRowIndex, int $afterRowIndex): void {
        $rows = $this->rows;

        if (!$this->isValidGroupRowIndex($rows, $fromGroupRowIndex) || !$this->isValidGroupRowIndex($rows, $toGroupRowIndex)) {
            return;
        }

        $result = $this->extractFieldFromPayloadRows($rows[$fromGroupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        $groupRows = $rows[$toGroupRowIndex]['group']['rows'] ?? [];
        $this->insertRowAfter($groupRows, $afterRowIndex, [$result['field']]);
        $rows[$toGroupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;
    }

    /**
     * Relocates a field to another position.
     *
     * @param integer $fromRowIndex
     * @param integer $fromFieldIndex
     * @param integer $toRowIndex
     * @param integer $toPosition
     *
     * @return void
     */
    public function relocateField(int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        if (!isset($this->rows[$fromRowIndex][$fromFieldIndex])) {
            return;
        }

        if ($fromRowIndex === $toRowIndex) {
            // Same-row reorder — no-op if the position wouldn't change.
            if ($fromFieldIndex === $toPosition || $fromFieldIndex + 1 === $toPosition) {
                return;
            }

            $rows = $this->rows;
            $row  = $rows[$fromRowIndex];
            [$field] = array_splice($row, $fromFieldIndex, 1);
            $insertAt = $toPosition > $fromFieldIndex ? $toPosition - 1 : $toPosition;
            array_splice($row, $insertAt, 0, [$field]);
            $rows[$fromRowIndex] = $row;
            $this->rows = $rows;
            return;
        }

        // Cross-row move — target row must have space.
        if (!isset($this->rows[$toRowIndex]) || count($this->rows[$toRowIndex]) >= 3) {
            return;
        }

        $rows = $this->rows;
        [$field] = array_splice($rows[$fromRowIndex], $fromFieldIndex, 1);

        if (empty($rows[$fromRowIndex])) {
            array_splice($rows, $fromRowIndex, 1);
            // Shift target index if source row was before it.
            if ($toRowIndex > $fromRowIndex) {
                $toRowIndex--;
            }
        }

        array_splice($rows[$toRowIndex], $toPosition, 0, [$field]);
        $this->rows = $rows;
    }

    /**
     * Move an existing canvas field into a new row inserted after $afterRowIndex.
     * Cleans up the source row if it becomes empty.
     * 
     * @param integer $fromRowIndex
     * @param integer $fromFieldIndex
     * @param integer $afterRowIndex
     * 
     * @return void
     */
    public function moveFieldToNewRow(int $fromRowIndex, int $fromFieldIndex, int $afterRowIndex): void {
        if (!isset($this->rows[$fromRowIndex][$fromFieldIndex])) {
            return;
        }

        $rows = $this->rows;
        [$field] = array_splice($rows[$fromRowIndex], $fromFieldIndex, 1);

        if (empty($rows[$fromRowIndex])) {
            array_splice($rows, $fromRowIndex, 1);
            if ($afterRowIndex >= $fromRowIndex) {
                $afterRowIndex--;
            }
        }

        $newRow = [$field];
        array_splice($rows, $afterRowIndex + 1, 0, [$newRow]);
        $this->rows = $rows;
    }

    /**
     * Insert a field at a specific position within an existing row (max 3 fields per row).
     */
    public function insertFieldIntoRow(int $rowIndex, int $position, string $fieldType): void {
        $newField = $this->makeFieldPayload($fieldType);

        if (!$newField) {
            return;
        }

        $rows = $this->rows;
        $targetPosition = max(0, min($position, count($rows[$rowIndex] ?? [])));

        if (!$this->insertFieldIntoPayloadRow($rows, $rowIndex, $position, $newField)) {
            return;
        }

        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => null,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $targetPosition,
        ]);
    }

    /**
     * Remove a field from a row, and remove the row if it becomes empty.
     * 
     * @param integer $rowIndex
     * @param integer $fieldIndex
     * 
     * @return void
     */
    public function removeField(int $rowIndex, int $fieldIndex): void {
        $rows = $this->rows;

        if (!$this->removeFieldFromPayloadRow($rows, $rowIndex, $fieldIndex)) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Build a normalized form structure for persistence.
     *
     * @return array
     */
    public function getFormStructure(): array {
        $rows = [];

        foreach (array_values($this->rows) as $rowIndex => $rowPayload) {
            if ($this->isGroupRow($rowPayload)) {
                $groupPayload = $rowPayload['group'] ?? [];
                $group = $this->makeFieldGroupFromPayload($groupPayload);

                $groupRows = Arr::map(array_values($groupPayload['rows'] ?? []), function (array $row, int $groupRowIndex): array {
                    return [
                        'position' => $groupRowIndex,
                        'fields'   => $this->serializeRowFromPayload($row),
                    ];
                });

                $rows[] = [
                    'position' => $rowIndex,
                    'type'     => 'group',
                    'group'    => [
                        'id'          => $groupPayload['id'] ?? null,
                        'handle'      => $groupPayload['handle'] ?? '',
                        'title'       => $groupPayload['title'] ?? '',
                        'description' => $groupPayload['description'] ?? '',
                        'definition'  => $group ? $group->toJson() : null,
                        'rows'        => $groupRows,
                    ],
                ];

                continue;
            }

            if (!is_array($rowPayload)) {
                continue;
            }

            $fields = $this->serializeRowFromPayload($rowPayload);

            $rows[] = [
                'position' => $rowIndex,
                'type'     => 'fields',
                'fields'   => $fields,
            ];
        }

        return [
            'rows' => $rows,
        ];
    }

    /**
     * Export the current form structure as JSON for storage.
     *
     * @param bool $pretty
     *
     * @return string
     */
    public function getFormStructureJson(bool $pretty = false): string {
        $flags = JSON_UNESCAPED_SLASHES;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        dd(json_encode($this->getFormStructure(), $flags) ?: '{"rows":[]}');

        return json_encode($this->getFormStructure(), $flags) ?: '{"rows":[]}';
    }

    /***************************
     * Helpers
     ***************************/
    /**
     * Create a payload array for a new field instance based on the given field type handle.
     *
     * @param string $handle
     *
     * @return array|null
     */
    private function makeFieldPayload(string $handle): ?array {
        $fieldType = $this->fieldTypes[$handle] ?? null;

        if (!$fieldType) {
            return null;
        }

        $payload = [
            'id'     => Str::uuid()->toString(),
            'handle' => $handle,
            'style'  => 'nice',
        ];

        if ($handle === 'repeater') {
            $payload['fields'] = [];
            $payload['defaultRows'] = [];
        }

        return $payload;
    }

    /**
     * Create a payload array for a field group container.
     */
    private function makeFieldGroupPayload(string $handle = ''): ?array {
        $title = 'Untitled Section';
        $description = '';
        $rows = [];

        if ($handle !== '') {
            if (!isset($this->fieldGroups[$handle])) {
                return null;
            }

            $title = $this->fieldGroups[$handle]['label'] ?? $title;

            try {
                $group = FieldGroupsRegister::checkout(Framework::get())->makeFrom($handle);
                $defaultFields = $group->getFields()
                    ->map(function ($field) {
                        if ($field instanceof Field) {
                            return $field->handle;
                        }

                        return is_string($field) ? $field : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($defaultFields)) {
                    $buffer = [];

                    foreach ($defaultFields as $fieldHandle) {
                        $fieldPayload = $this->makeFieldPayload($fieldHandle);

                        if (!$fieldPayload) {
                            continue;
                        }

                        $buffer[] = $fieldPayload;

                        if (count($buffer) === 3) {
                            $rows[] = $buffer;
                            $buffer = [];
                        }
                    }

                    if (!empty($buffer)) {
                        $rows[] = $buffer;
                    }
                }
            } catch (\Throwable) {
                // Keep payload creation resilient; fall back to an empty group.
            }
        }

        return [
            'id'          => Str::uuid()->toString(),
            'handle'      => $handle,
            'title'       => $title,
            'description' => $description,
            'rows'        => $rows,
        ];
    }

    /**
     * Select a repeater field.
     *
     * @param array<string, int|null> $location
     */
    private function selectRepeaterField(array $location): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            $this->closeRepeaterBuilder();
            return;
        }

        $this->activeRepeater = $location;
    }

    /**
     * Select a field for settings. Repeater fields route to repeater builder.
     *
     * @param array<string, int|null> $location
     */
    private function selectFieldSettings(array $location): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload)) {
            $this->closeFieldSettings();
            $this->closeRepeaterBuilder();
            return;
        }

        if (($payload['handle'] ?? null) === 'repeater') {
            $this->closeFieldSettings();
            $this->selectRepeaterField($location);
            return;
        }

        $this->closeRepeaterBuilder();
        $this->activeFieldSettings = $location;
    }

    /**
     * If a settings panel is open, focus it on the newly added field.
     *
     * @param array<string, int|null> $location
     */
    private function focusNewlyAddedFieldSettings(array $location): void {
        if ($this->activeFieldSettings === null && $this->activeRepeater === null) {
            return;
        }

        $this->selectFieldSettings($location);
    }

    /**
     * Return the payload for a top-level or grouped field location.
     *
     * @param array<string, int|null> $location
     */
    private function getFieldPayloadAt(array $location): ?array {
        $groupRowIndex = $location['groupRowIndex'] ?? null;
        $rowIndex = $location['rowIndex'] ?? null;
        $fieldIndex = $location['fieldIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return null;
        }

        if ($groupRowIndex === null) {
            return $this->rows[$rowIndex][$fieldIndex] ?? null;
        }

        if (!is_int($groupRowIndex) || !$this->isValidGroupRowIndex($this->rows, $groupRowIndex)) {
            return null;
        }

        return $this->rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex] ?? null;
    }

    /**
     * Mutate a field payload at the given location.
     *
     * @param array<string, int|null> $location
     */
    private function mutateFieldPayloadAt(array $location, callable $mutator): bool {
        $rows = $this->rows;
        $groupRowIndex = $location['groupRowIndex'] ?? null;
        $rowIndex = $location['rowIndex'] ?? null;
        $fieldIndex = $location['fieldIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return false;
        }

        if ($groupRowIndex === null) {
            if (!isset($rows[$rowIndex][$fieldIndex]) || !is_array($rows[$rowIndex][$fieldIndex])) {
                return false;
            }

            $rows[$rowIndex][$fieldIndex] = $mutator($rows[$rowIndex][$fieldIndex]);
            $this->rows = $rows;

            return true;
        }

        if (!is_int($groupRowIndex) || !$this->isValidGroupRowIndex($rows, $groupRowIndex)) {
            return false;
        }

        if (!isset($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex]) || !is_array($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex])) {
            return false;
        }

        $rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex] = $mutator($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex]);
        $this->rows = $rows;

        return true;
    }

    /**
     * Mutate the active repeater payload if one is selected.
     */
    private function mutateActiveRepeaterPayload(callable $mutator): void {
        if (!$this->activeRepeater) {
            return;
        }

        $updated = $this->mutateFieldPayloadAt($this->activeRepeater, function (array $payload) use ($mutator): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            return $mutator($payload);
        });

        if (!$updated) {
            $this->closeRepeaterBuilder();
        }
    }

    /**
     * Validate that activeFieldSettings points to a valid field. Close panel if invalid.
     */
    private function validateActiveFieldSettings(): void {
        if (!$this->activeFieldSettings) {
            return;
        }

        $payload = $this->getFieldPayloadAt($this->activeFieldSettings);

        // If the field no longer exists or is a repeater, close the panel
        if (!is_array($payload) || ($payload['handle'] ?? null) === 'repeater') {
            $this->closeFieldSettings();
            return;
        }

        // Verify the field can be created (has valid type)
        if (!$this->makeFieldFromPayload($payload)) {
            $this->closeFieldSettings();
        }
    }

    /**
     * Build a view model for the active generic field settings panel.
     */
    private function getActiveFieldSettingsViewModel(): ?array {
        if (!$this->activeFieldSettings) {
            return null;
        }

        $payload = $this->getFieldPayloadAt($this->activeFieldSettings);

        if (!is_array($payload) || ($payload['handle'] ?? null) === 'repeater') {
            return null;
        }

        $field = $this->makeFieldFromPayload($payload);

        if (!$field) {
            return null;
        }

        $handle = (string) ($payload['handle'] ?? '');
        $optionHandles = ['select', 'multi_select', 'radio', 'checkboxes'];
        $placeholderHandles = ['text', 'email', 'url', 'number', 'password', 'date', 'time'];

        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $optionsText = collect($options)
            ->map(fn ($label, $value) => is_string($value) ? ($value . '|' . (string) $label) : (string) $label)
            ->implode("\n");

        return [
            'location' => $this->activeFieldSettings,
            'field' => $field,
            'payload' => $payload,
            'supportsOptions' => in_array($handle, $optionHandles, true),
            'supportsPlaceholder' => in_array($handle, $placeholderHandles, true),
            'supportsRows' => $handle === 'textarea',
            'optionsText' => $optionsText,
        ];
    }

    /**
     * Parse newline options text into value => label pairs.
     */
    private function parseOptionsText(string $text): array {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $options = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$value, $label] = array_map('trim', explode('|', $line, 2));
            } else {
                $label = $line;
                $value = Str::slug($line);
            }

            if ($value === '') {
                $value = Str::slug($label);
            }

            $options[$value] = $label;
        }

        return $options;
    }

    /**
     * Dispatch a browser event to sync TomSelect for a specific field location.
     * This keeps wire:ignore-managed select DOM in sync with settings changes.
     *
     * @param array<string, int|null> $location
     */
    private function dispatchTomSelectFieldSyncForLocation(array $location): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload)) {
            return;
        }

        $handle = (string) ($payload['handle'] ?? '');

        if (!in_array($handle, ['select', 'multi_select'], true)) {
            return;
        }

        $rowIndex = $location['rowIndex'] ?? null;
        $fieldIndex = $location['fieldIndex'] ?? null;
        $groupRowIndex = $location['groupRowIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return;
        }

        $locationKey = is_int($groupRowIndex)
            ? sprintf('group-%d-%d-%d', $groupRowIndex, $rowIndex, $fieldIndex)
            : sprintf('%d-%d', $rowIndex, $fieldIndex);

        $options = [];

        foreach ((array) ($payload['options'] ?? []) as $optionValue => $optionLabel) {
            $options[] = [
                'value' => (string) $optionValue,
                'label' => (string) $optionLabel,
            ];
        }

        $isMultiple = $handle === 'multi_select' || (bool) ($payload['multiple'] ?? false);
        $isAdvanced = $isMultiple ? true : (bool) ($payload['advanced'] ?? false);

        $this->dispatch('tomselect-field-sync',
            locationKey: $locationKey,
            options: $options,
            value: $payload['value'] ?? null,
            advanced: $isAdvanced,
            allowAdd: (bool) ($payload['allowAdd'] ?? false),
            multiple: $isMultiple,
            disabled: (bool) ($payload['disabled'] ?? false),
            required: (bool) ($payload['required'] ?? false)
        );
    }

    /**
     * Dispatch a browser event to sync TomSelect for a repeater column across all rows.
     * Updates select/multi-select fields in repeater rows when column settings change.
     *
     * @param array<string, int|null> $location Location of the repeater field
     * @param int $columnIndex Index of the column that changed
     */
    private function dispatchTomSelectRepeaterColumnSyncForLocation(array $location, int $columnIndex): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return;
        }

        $columns = array_values(array_filter($payload['fields'] ?? [], 'is_array'));

        if (!isset($columns[$columnIndex])) {
            return;
        }

        $columnPayload = $columns[$columnIndex];
        $handle = (string) ($columnPayload['handle'] ?? '');

        if (!in_array($handle, ['select', 'multi_select'], true)) {
            return;
        }

        $rowIndex = $location['rowIndex'] ?? null;
        $fieldIndex = $location['fieldIndex'] ?? null;
        $groupRowIndex = $location['groupRowIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return;
        }

        $repeaterLocationKey = is_int($groupRowIndex)
            ? sprintf('group-%d-%d-%d', $groupRowIndex, $rowIndex, $fieldIndex)
            : sprintf('%d-%d', $rowIndex, $fieldIndex);

        $options = [];

        foreach ((array) ($columnPayload['options'] ?? []) as $optionValue => $optionLabel) {
            $options[] = [
                'value' => (string) $optionValue,
                'label' => (string) $optionLabel,
            ];
        }

        $isMultiple = $handle === 'multi_select' || (bool) ($columnPayload['multiple'] ?? false);
        $isAdvanced = $isMultiple ? true : (bool) ($columnPayload['advanced'] ?? false);

        $this->dispatch('tomselect-repeater-column-sync',
            repeaterLocationKey: $repeaterLocationKey,
            columnIndex: $columnIndex,
            columnName: (string) ($columnPayload['name'] ?? $columnPayload['handle'] ?? ''),
            options: $options,
            advanced: $isAdvanced,
            allowAdd: (bool) ($columnPayload['allowAdd'] ?? false),
            multiple: $isMultiple,
            disabled: (bool) ($columnPayload['disabled'] ?? false),
            required: (bool) ($columnPayload['required'] ?? false)
        );
    }

    /**
     * Dispatch a browser event to sync TomSelect value for a repeater row field.
     * Updates select/multi-select field values in all instances of a repeater row.
     *
     * @param array<string, int|null> $location Location of the repeater field
     * @param int $rowIndex Index of the row that changed
     * @param string $fieldName Name of the field that changed
     * @param mixed $value New value
     */
    private function dispatchTomSelectRepeaterRowValueSyncForLocation(array $location, int $rowIndex, string $fieldName, mixed $value): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return;
        }

        $columns = array_values(array_filter($payload['fields'] ?? [], 'is_array'));
        
        // Find which column index matches this field name
        $columnIndex = null;
        foreach ($columns as $idx => $columnPayload) {
            if ((string) ($columnPayload['name'] ?? $columnPayload['handle'] ?? '') === $fieldName) {
                $columnIndex = $idx;
                break;
            }
        }

        if ($columnIndex === null) {
            return;
        }

        $rowIndex = $location['rowIndex'] ?? null;
        $fieldIndex = $location['fieldIndex'] ?? null;
        $groupRowIndex = $location['groupRowIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return;
        }

        $repeaterLocationKey = is_int($groupRowIndex)
            ? sprintf('group-%d-%d-%d', $groupRowIndex, $rowIndex, $fieldIndex)
            : sprintf('%d-%d', $rowIndex, $fieldIndex);

        // Normalize value for transmission
        $normalizedValue = is_array($value) 
            ? array_map(fn ($v) => (string) $v, $value)
            : ((is_string($value) || is_numeric($value)) ? (string) $value : null);

        $this->dispatch('tomselect-repeater-row-value-sync',
            repeaterLocationKey: $repeaterLocationKey,
            rowIndex: $rowIndex,
            columnIndex: $columnIndex,
            fieldName: $fieldName,
            value: $normalizedValue
        );
    }

    /**
     * Get fresh hydrated repeater rows for canvas rendering.
     */
    public function getCanvasRepeaterRows(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex = null): array {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex' => $rowIndex,
            'fieldIndex' => $fieldIndex,
        ];

        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return [];
        }

        $field = $this->makeFieldFromPayload($payload);

        if (!$field instanceof Repeater) {
            return [];
        }

        return $field->buildRows();
    }

    /**
     * Build a hydrated repeater view model for the properties panel.
     */
    private function getActiveRepeaterViewModel(): ?array {
        if (!$this->activeRepeater) {
            return null;
        }

        $payload = $this->getFieldPayloadAt($this->activeRepeater);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return null;
        }

        $field = $this->makeFieldFromPayload($payload);

        if (!$field instanceof Repeater) {
            return null;
        }

        return [
            'location' => $this->activeRepeater,
            'field' => $field,
            'columns' => $field->getFields()->values()->all(),
            'columnPayloads' => array_values(array_filter($payload['fields'] ?? [], 'is_array')),
            'defaultRows' => $payload['defaultRows'] ?? ($payload['value'] ?? []),
            'previewRows' => $field->buildRows(),
        ];
    }

    /**
     * Create a Field instance from a payload array.
     *
     * @param array $payload
     *
     * @return Field|null
     */
    private function makeFieldFromPayload(array $payload): ?Field {
        $handle = $payload['handle'] ?? null;
        $id     = $payload['id'] ?? null;

        if (!$handle || !$id) {
            return null;
        }

        $fieldType = $this->fieldTypes[$handle] ?? null;

        if (!$fieldType) {
            return null;
        }

        $fieldInstance = Fields::checkout(Framework::get())->makeFrom($fieldType);

        $fieldInstance->name((string) ($payload['name'] ?? $handle));
        $fieldInstance->id($id);

        if (!empty($payload['label'])) {
            $fieldInstance->label($payload['label']);
        }

        if (array_key_exists('helpText', $payload)) {
            $fieldInstance->helpText((string) ($payload['helpText'] ?? ''), (string) ($payload['helpTextPosition'] ?? 'bottom'));
        }

        if (array_key_exists('value', $payload)) {
            $fieldInstance->value($payload['value']);
        }

        if (array_key_exists('required', $payload)) {
            $fieldInstance->required((bool) $payload['required']);
        }

        if (array_key_exists('disabled', $payload)) {
            $fieldInstance->disabled((bool) $payload['disabled']);
        }

        if (!empty($payload['width'])) {
            $fieldInstance->width((string) $payload['width']);
        }

        if (!empty($payload['style'])) {
            $fieldInstance->style($payload['style']);
        }

        if (!empty($payload['placeholder']) && method_exists($fieldInstance, 'placeholder')) {
            $fieldInstance->placeholder((string) $payload['placeholder']);
        }

        if (array_key_exists('options', $payload) && is_array($payload['options']) && method_exists($fieldInstance, 'options')) {
            $fieldInstance->options($payload['options']);
        }

        if (array_key_exists('rows', $payload) && method_exists($fieldInstance, 'rows')) {
            $fieldInstance->rows(max(1, (int) $payload['rows']));
        }

        // For select/multi-select fields, handle advanced mode and allowAdd
        if ($handle === 'multi_select') {
            // Multi-select is always advanced
            if (method_exists($fieldInstance, 'advanced')) {
                $fieldInstance->advanced(true);
            }
        } elseif ($handle === 'select') {
            // Regular select can be toggled
            if (array_key_exists('advanced', $payload) && method_exists($fieldInstance, 'advanced')) {
                $fieldInstance->advanced((bool) $payload['advanced']);
            }
        }

        if (array_key_exists('allowAdd', $payload) && method_exists($fieldInstance, 'allowAdd')) {
            $fieldInstance->allowAdd((bool) $payload['allowAdd']);
        }

        if ($fieldInstance instanceof Repeater) {
            $this->hydrateRepeaterField($fieldInstance, $payload);
        }

        return $fieldInstance;
    }

    /**
     * Hydrate repeater-specific child fields and default rows from payload.
     */
    private function hydrateRepeaterField(Repeater $fieldInstance, array $payload): void {
        $childPayloads = array_values(array_filter($payload['fields'] ?? [], 'is_array'));

        foreach ($childPayloads as $childPayload) {
            $childField = $this->makeFieldFromPayload($childPayload);

            if ($childField) {
                $fieldInstance->attach($childField);
            }
        }

        if (array_key_exists('defaultRows', $payload)) {
            $fieldInstance->value(is_array($payload['defaultRows']) ? $payload['defaultRows'] : []);
        }
    }

    /**
     * Create a FieldGroup instance from a group payload.
     */
    private function makeFieldGroupFromPayload(array $payload): ?FieldGroup {
        $handle = $payload['handle'] ?? '';

        try {
            if ($handle !== '' && isset($this->fieldGroups[$handle])) {
                $group = FieldGroupsRegister::checkout(Framework::get())->makeFrom($handle);
            } else {
                $group = new FieldGroup(Framework::get(), []);
            }

            if ($handle !== '') {
                $group->handle($handle);
            }

            $group->title($payload['title'] ?? 'Untitled Section');
            $group->description($payload['description'] ?? '');

            return $group;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hydrate field objects from row payloads for rendering/export.
     *
     * @return array<int, array<int, Field>>
     */
    private function getHydratedRows(): array {
        $fieldRows = Arr::where($this->rows, fn ($row) => is_array($row) && !$this->isGroupRow($row));

        $hydratedRows = $this->hydratePayloadRows($fieldRows);

        // Keep a flat collection of instantiated fields available on the class.
        $this->elements = collect(Arr::flatten($hydratedRows, 1));

        return $hydratedRows;
    }

    /**
     * Determine if a row payload represents a group row.
     */
    private function isGroupRow(mixed $rowPayload): bool {
        return is_array($rowPayload)
            && ($rowPayload['_type'] ?? null) === 'group'
            && is_array($rowPayload['group'] ?? null);
    }

    /**
     * Insert a row payload after the given row index.
     */
    private function insertRowAfter(array &$rows, int $afterRowIndex, array $rowPayload): void {
        array_splice($rows, $afterRowIndex + 1, 0, [$rowPayload]);
    }

    /**
     * Insert a field payload into a row (max 3 fields).
     */
    private function insertFieldIntoPayloadRow(array &$rows, int $rowIndex, int $position, array $payload): bool {
        if (!isset($rows[$rowIndex]) || !is_array($rows[$rowIndex]) || count($rows[$rowIndex]) >= 3) {
            return false;
        }

        array_splice($rows[$rowIndex], $position, 0, [$payload]);

        return true;
    }

    /**
     * Remove a field payload from a row and prune empty rows.
     */
    private function removeFieldFromPayloadRow(array &$rows, int $rowIndex, int $fieldIndex): bool {
        if (!isset($rows[$rowIndex][$fieldIndex])) {
            return false;
        }

        array_splice($rows[$rowIndex], $fieldIndex, 1);

        if (empty($rows[$rowIndex])) {
            array_splice($rows, $rowIndex, 1);
        }

        return true;
    }

    /**
     * Extract a field payload from rows and prune the row if it becomes empty.
     *
     * @return array{field: array, rowRemoved: bool}|null
     */
    private function extractFieldFromPayloadRows(array &$rows, int $rowIndex, int $fieldIndex): ?array {
        if (!isset($rows[$rowIndex][$fieldIndex])) {
            return null;
        }

        [$field] = array_splice($rows[$rowIndex], $fieldIndex, 1);
        $rowRemoved = false;

        if (empty($rows[$rowIndex])) {
            array_splice($rows, $rowIndex, 1);
            $rowRemoved = true;
        }

        return [
            'field' => $field,
            'rowRemoved' => $rowRemoved,
        ];
    }

    /**
     * Validate that an index points to a group row.
     */
    private function isValidGroupRowIndex(array $rows, int $groupRowIndex): bool {
        return isset($rows[$groupRowIndex]) && $this->isGroupRow($rows[$groupRowIndex]);
    }

    /**
     * Hydrate payload rows into field objects.
     *
     * @return array<int, array<int, Field>>
     */
    private function hydratePayloadRows(array $rows): array {
        return Arr::map($rows, function (array $row): array {
            $fields = Arr::map($row, function ($payload) {
                if (!is_array($payload)) {
                    return null;
                }

                return $this->makeFieldFromPayload($payload);
            });

            return array_values(Arr::where($fields, fn ($field) => !is_null($field)));
        });
    }

    /**
     * Serialize a row payload into normalized field arrays.
     */
    private function serializeRowFromPayload(array $rowPayload): array {
        $hydratedFields = $this->hydratePayloadRows([array_values($rowPayload)]);
        $fields = array_values($hydratedFields[0] ?? []);

        return Arr::map($fields, fn (Field $field, int $fieldIndex): array => $this->serializeField($field, $fieldIndex));
    }

    /**
     * Serialize a field instance for form structure persistence.
     */
    private function serializeField(Field $field, int $position): array {
        $name = null;

        try {
            $name = $field->getName(false);
        } catch (\Throwable) {
            $name = null;
        }

        $payload = [
            'id'               => $field->getId(),
            'handle'           => $field->handle,
            'label'            => $field->getLabel(),
            'name'             => $name,
            'helpText'         => $field->getHelpText(),
            'helpTextPosition' => $field->getHelpTextPosition(),
            'value'            => $field->getValue(),
            'options'          => method_exists($field, 'getOptions') ? $field->getOptions() : null,
            'required'         => $field->isRequired(),
            'disabled'         => $field->isDisabled(),
            'width'            => $field->getWidth(),
            'variation'        => $field->getVariation(),
            'position'         => $position,
        ];

        if ($field instanceof Repeater) {
            $payload['fields'] = $field->getFields()
                ->values()
                ->map(fn (Field $childField, int $childIndex): array => $this->serializeField($childField, $childIndex))
                ->all();
            $payload['defaultRows'] = is_array($field->getValue()) ? $field->getValue() : [];
        }

        return $payload;
    }
}