<?php

namespace MM\Meros\App\Toolbox\Concerns;

use MM\Meros\App\Fields\Repeater;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Manages the repeater builder panel: open/close, column CRUD, default row CRUD,
 * and the active repeater view model.
 *
 * Expects the using class to have:
 *   - array|null  $activeRepeater
 *   - int|null    $activeRepeaterRow
 *   - PayloadHydrator $hydrator
 *   - methods: mutateFieldPayloadAt(), mutateActiveRepeaterPayload(),
 *              getFieldPayloadAt(), selectRepeaterField(),
 *              dispatchTomSelectRepeaterColumnSyncForLocation(),
 *              dispatchTomSelectRepeaterRowValueSyncForLocation(),
 *              parseOptionsText()
 */
trait ManagesRepeaterBuilder {
    /**
     * Close the repeater builder panel.
     * 
     * @return void
     */
    public function closeRepeaterBuilder(): void {
        $this->activeRepeater    = null;
        $this->activeRepeaterRow = null;
    }

    /**
     * Select a row for editing in the repeater builder.
     * 
     * @param int $rowIndex The index of the repeater default row to select for editing.
     * 
     * @return void
     */
    public function selectRepeaterRow(int $rowIndex): void {
        $this->activeRepeaterRow = $rowIndex;
    }

    /**
     * Close the repeater row editor.
     * 
     * @return void
     */
    public function closeRepeaterRow(): void {
        $this->activeRepeaterRow = null;
    }

    /**
     * Move a default row in the active repeater preview.
     * 
     * @param int $fromIndex The index of the row to move.
     * @param int $toIndex   The index to move the row to.
     * 
     * @return void
     */
    public function moveRepeaterDefaultRow(int $fromIndex, int $toIndex): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($fromIndex, $toIndex): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$fromIndex]) || $fromIndex === $toIndex) {
                return $repeaterPayload;
            }

            $movingRow = $rows[$fromIndex];
            unset($rows[$fromIndex]);
            $rows = array_values($rows);
            array_splice($rows, $toIndex, 0, [$movingRow]);

            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value']       = $rows;
            return $repeaterPayload;
        });
    }

    /**
     * Insert a column into the active repeater.
     * 
     * @param int $position The position to insert the column at.
     * @param string $handle The handle of the field to insert.
     * 
     * @return void
     */
    public function addRepeaterColumnAt(int $position, string $handle): void {
        $payload = $this->hydrator->makeFieldPayload($handle);

        if (!$payload || $handle === 'repeater') {
            return;
        }

        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($payload, $position): array {
            $columns  = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $insertAt = max(0, min($position, count($columns)));

            array_splice($columns, $insertAt, 0, [$payload]);

            $repeaterPayload['fields'] = $columns;
            return $repeaterPayload;
        });
    }

    /**
     * Remove a column from the active repeater.
     * 
     * @param int $columnIndex The index of the column to remove.
     * 
     * @return void
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
     * 
     * @param int $fromIndex The index of the column to move.
     * @param int $toIndex   The index to move the column to.
     * 
     * @return void
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
     * 
     * @param int    $columnIndex The index of the column to update.
     * @param string $key         The setting key to update.
     * @param mixed  $value       The new value for the setting.
     * 
     * @return void
     */
    public function updateRepeaterColumnSetting(int $columnIndex, string $key, mixed $value): void {
        $shouldDispatchSync = false;

        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($columnIndex, $key, $value, &$shouldDispatchSync): array {
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));

            if (!isset($columns[$columnIndex])) {
                return $repeaterPayload;
            }

            $column  = $columns[$columnIndex];
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
                    $repeaterPayload['value']       = $defaultRows;
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

            $repeaterPayload['_fieldVersion'] = ($repeaterPayload['_fieldVersion'] ?? 0) + 1;
            return $repeaterPayload;
        });

        if ($shouldDispatchSync && $this->activeRepeater) {
            $this->dispatchTomSelectRepeaterColumnSyncForLocation($this->activeRepeater, $columnIndex);
        }
    }

    /**
     * Add a default row and open its settings panel for editing.
     * 
     * @return void
     */
    public function addRepeaterRowAndEdit(): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload): array {
            $rows    = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $newRow  = [];

            foreach ($columns as $columnPayload) {
                $columnName = $columnPayload['name'] ?? $columnPayload['handle'] ?? null;

                if (is_string($columnName) && $columnName !== '') {
                    $newRow[$columnName] = null;
                }
            }

            $rows[] = $newRow;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value']       = $rows;
            $this->activeRepeaterRow        = count($rows) - 1;

            return $repeaterPayload;
        });
    }

    /**
     * Append a default row to the active repeater preview.
     * 
     * @return void
     */
    public function addRepeaterDefaultRow(): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload): array {
            $rows    = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($repeaterPayload['fields'] ?? [], 'is_array'));
            $newRow  = [];

            foreach ($columns as $columnPayload) {
                $columnName = $columnPayload['name'] ?? $columnPayload['handle'] ?? null;

                if (is_string($columnName) && $columnName !== '') {
                    $newRow[$columnName] = null;
                }
            }

            $rows[] = $newRow;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value']       = $rows;

            return $repeaterPayload;
        });
    }

    /**
     * Remove a default row from the active repeater preview.
     *
     * @param int $rowIndex The index of the row to remove.
     * 
     * @return void
     */
    public function removeRepeaterDefaultRow(int $rowIndex): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($rowIndex): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$rowIndex])) {
                return $repeaterPayload;
            }

            array_splice($rows, $rowIndex, 1);
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value']       = $rows;

            return $repeaterPayload;
        });
    }

    /**
     * Update a single default row value in the active repeater preview.
     * 
     * @param int    $rowIndex  The index of the row to update.
     * @param string $fieldName The name of the field to update.
     * @param mixed  $value     The new value for the field.
     * 
     * @return void
     */
    public function updateRepeaterDefaultRowValue(int $rowIndex, string $fieldName, mixed $value): void {
        $this->mutateActiveRepeaterPayload(function (array $repeaterPayload) use ($rowIndex, $fieldName, $value): array {
            $rows = array_values(array_filter($repeaterPayload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$rowIndex])) {
                return $repeaterPayload;
            }

            $rows[$rowIndex][$fieldName]    = $value;
            $repeaterPayload['defaultRows'] = $rows;
            $repeaterPayload['value']       = $rows;

            return $repeaterPayload;
        });

        if ($this->activeRepeater) {
            $this->dispatchTomSelectRepeaterRowValueSyncForLocation($this->activeRepeater, $rowIndex, $fieldName, $value);
        }
    }

    /**
     * Build a hydrated repeater view model for the properties panel.
     * 
     * @return array|null The view model containing the repeater field, its columns, default rows, and preview rows, or null if no active repeater is selected.
     */
    private function getActiveRepeaterViewModel(): ?array {
        if (!$this->activeRepeater) {
            return null;
        }

        $payload = $this->getFieldPayloadAt($this->activeRepeater);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return null;
        }

        $field = $this->hydrator->makeFieldFromPayload($payload);

        if (!$field instanceof Repeater) {
            return null;
        }

        return [
            'location'      => $this->activeRepeater,
            'field'         => $field,
            'columns'       => $field->getFields()->values()->all(),
            'columnPayloads' => array_values(array_filter($payload['fields'] ?? [], 'is_array')),
            'defaultRows'   => $payload['defaultRows'] ?? ($payload['value'] ?? []),
            'previewRows'   => $field->buildRows(),
        ];
    }

    /**
     * Mutate the active repeater payload if one is selected.
     * 
     * @param callable $mutator A callback that receives the current repeater payload and returns the updated payload.
     * 
     * @return void
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
     * Select a repeater field by location.
     *
     * @param array<string, int|null> $location
     * 
     * @return void
     */
    private function selectRepeaterField(array $location): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            $this->closeRepeaterBuilder();
            return;
        }

        $this->activeRepeater = $location;
    }
}
