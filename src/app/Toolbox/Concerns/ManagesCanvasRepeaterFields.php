<?php

namespace MM\Meros\App\Toolbox\Concerns;

use MM\Meros\App\Fields\Repeater;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Handles canvas repeater field row operations: add, remove, reorder, update value,
 * and building the hydrated rows for rendering.
 *
 * Expects the using class to have:
 *   - PayloadHydrator $hydrator
 *   - methods: mutateFieldPayloadAt(), getFieldPayloadAt(),
 *              dispatchTomSelectRepeaterRowValueSyncForLocation()
 */
trait ManagesCanvasRepeaterFields {
    /**
     * Update a single row value in a canvas repeater field.
     * 
     * @param int|null $rowIndex         The index of the row containing the repeater field.
     * @param int|null $fieldIndex       The index of the repeater field within the row.
     * @param int|null $groupRowIndex    The index of the group row containing the repeater field, if applicable.
     * @param int      $repeaterRowIndex The index of the row within the repeater to update.
     * @param string   $fieldName        The name of the field within the repeater row to update.
     * @param mixed    $value            The new value to set for the specified field in the repeater row.
     * 
     * @return void
     */
    public function updateFieldRepeaterRowValue(
        ?int $rowIndex, 
        ?int $fieldIndex, 
        ?int $groupRowIndex, 
        int $repeaterRowIndex, 
        string $fieldName, 
        mixed $value
    ): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
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
            $payload['value']       = $rows;

            return $payload;
        });

        $this->dispatchTomSelectRepeaterRowValueSyncForLocation($location, $repeaterRowIndex, $fieldName, $value);
    }

    /**
     * Add a row to a field's repeater in the canvas.
     * 
     * @param int|null $rowIndex       The index of the row containing the repeater field.
     * @param int|null $fieldIndex     The index of the repeater field within the row.
     * @param int|null $groupRowIndex  The index of the group row containing the repeater field, if applicable.
     * 
     * @return void
     */
    public function addFieldRepeaterRow(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex = null): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows    = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));
            $columns = array_values(array_filter($payload['fields'] ?? [], 'is_array'));

            if (empty($columns)) {
                $defaultTextColumn = $this->hydrator->makeFieldPayload('text');

                if (is_array($defaultTextColumn)) {
                    $columns[]        = $defaultTextColumn;
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
            $payload['value']       = $rows;

            return $payload;
        });
    }

    /**
     * Remove a row from a field's repeater in the canvas.
     * 
     * @param int|null $rowIndex       The index of the row containing the repeater field.
     * @param int|null $fieldIndex     The index of the repeater field within the row.
     * @param int|null $groupRowIndex  The index of the group row containing the repeater field, if applicable.
     * @param int      $removeRowIndex The index of the row within the repeater to remove.
     * 
     * @return void
     */
    public function removeFieldRepeaterRow(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex, int $removeRowIndex): void {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
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
                $payload['value']       = $rows;
            }

            return $payload;
        });
    }

    /**
     * Reorder rows in a field's repeater in the canvas.
     * 
     * @param int|null $rowIndex       The index of the row containing the repeater field.
     * @param int|null $fieldIndex     The index of the repeater field within the row.
     * @param int|null $groupRowIndex  The index of the group row containing the repeater field, if applicable.
     * @param int      $fromIndex      The current index of the row within the repeater to move.
     * @param int      $toIndex        The target index within the repeater to move the row to.
     * 
     * @return void
     */
    public function moveFieldRepeaterRow(
        ?int $rowIndex, 
        ?int $fieldIndex, 
        ?int $groupRowIndex, 
        int $fromIndex, 
        int $toIndex
    ): void{
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($fromIndex, $toIndex): array {
            if (($payload['handle'] ?? null) !== 'repeater') {
                return $payload;
            }

            $rows = array_values(array_filter($payload['defaultRows'] ?? [], 'is_array'));

            if (!isset($rows[$fromIndex]) || $fromIndex === $toIndex) {
                return $payload;
            }

            $movingRow = $rows[$fromIndex];
            unset($rows[$fromIndex]);
            $rows = array_values($rows);
            array_splice($rows, $toIndex, 0, [$movingRow]);

            $payload['defaultRows'] = $rows;
            $payload['value']       = $rows;

            return $payload;
        });
    }

    /**
     * Get fresh hydrated repeater rows for canvas rendering.
     * 
     * @param int|null $rowIndex       The index of the row containing the repeater field.
     * @param int|null $fieldIndex     The index of the repeater field within the row.
     * @param int|null $groupRowIndex  The index of the group row containing the repeater field, if applicable.
     * 
     * @return array<int, array<string, mixed>> An array of hydrated repeater rows, where each row is an associative array of field values keyed by field name.
     */
    public function getCanvasRepeaterRows(?int $rowIndex, ?int $fieldIndex, ?int $groupRowIndex = null): array {
        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ];

        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return [];
        }

        $field = $this->hydrator->makeFieldFromPayload($payload);

        if (!$field instanceof Repeater) {
            return [];
        }

        return $field->buildRows();
    }
}
