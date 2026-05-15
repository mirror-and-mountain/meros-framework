<?php

namespace MM\Meros\App\Toolbox\Concerns;

use MM\Meros\App\Toolbox\Helpers\RowMutator;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Handles all top-level (non-group) canvas field operations:
 * add, insert, remove, relocate, and move to new rows.
 *
 * Expects the using class to have:
 *   - array $rows
 *   - methods: makeFieldPayload(), insertRowAfter(), insertFieldIntoPayloadRow(),
 *              removeFieldFromPayloadRow(), focusNewlyAddedFieldSettings()
 */
trait ManagesCanvasFields {
    /**
     * Adds a new field to a new row inserted after $afterRowIndex.
     * If $afterRowIndex is -1, adds to the beginning.
     * 
     * @param int    $afterRowIndex The index after which to insert the new row with the field.
     * @param string $handle        The field type handle to create the payload for.
     * 
     * @return void
     */
    public function addFieldToNewRow(int $afterRowIndex, string $handle): void {
        $payload = $this->hydrator->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows        = $this->rows;
        $newRowIndex = max(0, min($afterRowIndex + 1, count($rows)));
        RowMutator::insertRowAfter($rows, $afterRowIndex, [$payload]);
        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => null,
            'rowIndex'      => $newRowIndex,
            'fieldIndex'    => 0,
        ]);
    }

    /**
     * Insert a field at a specific position within an existing row (max 3 fields per row).
     * 
     * @param int    $rowIndex  The index of the row to insert the field into.
     * @param int    $position  The position within the row to insert the field (0-3).
     * @param string $fieldType The field type handle to create the payload for.
     * 
     * @return void
     */
    public function insertFieldIntoRow(int $rowIndex, int $position, string $fieldType): void {
        $newField = $this->hydrator->makeFieldPayload($fieldType);

        if (!$newField) {
            return;
        }

        $rows           = $this->rows;
        $targetPosition = max(0, min($position, count($rows[$rowIndex] ?? [])));

        if (!RowMutator::insertFieldIntoPayloadRow($rows, $rowIndex, $position, $newField)) {
            return;
        }

        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => null,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $targetPosition,
        ]);
    }

    /**
     * Remove a field from a row and remove the row if it becomes empty.
     * 
     * @param int $rowIndex   The index of the row containing the field to remove.
     * @param int $fieldIndex The index of the field within the row to remove.
     * 
     * @return void
     */
    public function removeField(int $rowIndex, int $fieldIndex): void {
        $rows = $this->rows;

        if (!RowMutator::removeFieldFromPayloadRow($rows, $rowIndex, $fieldIndex)) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Relocate a field to another position within the same row or an adjacent row.
     * 
     * @param int $fromRowIndex   The index of the row containing the field to move.
     * @param int $fromFieldIndex The index of the field within the row to move.
     * @param int $toRowIndex     The index of the row to move the field to.
     * @param int $toPosition     The position within the target row to insert the field.
     * 
     * @return void
     */
    public function relocateField(int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        if (!isset($this->rows[$fromRowIndex][$fromFieldIndex])) {
            return;
        }

        if ($fromRowIndex === $toRowIndex) {
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

        if (!isset($this->rows[$toRowIndex]) || count($this->rows[$toRowIndex]) >= 3) {
            return;
        }

        $rows = $this->rows;
        [$field] = array_splice($rows[$fromRowIndex], $fromFieldIndex, 1);

        if (empty($rows[$fromRowIndex])) {
            array_splice($rows, $fromRowIndex, 1);

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
     * @param int $fromRowIndex   The index of the row containing the field to move.
     * @param int $fromFieldIndex The index of the field within the row to move
     * @param int $afterRowIndex  The index after which to insert the new row with the field.
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
}
