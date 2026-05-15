<?php

namespace MM\Meros\App\Toolbox\Helpers;

/**
 * Helpers for manipulating payload row structures.
 */
class RowMutator {
    /**
     * Determine if a row payload represents a group row.
     * 
     * @param mixed $rowPayload The row payload to check.
     * 
     * @return bool True if the payload is a group row, false otherwise.
     */
    public static function isGroupRow(mixed $rowPayload): bool {
        return is_array($rowPayload)
            && ($rowPayload['_type'] ?? null) === 'group'
            && is_array($rowPayload['group'] ?? null);
    }

    /**
     * Validate that an index points to a group row in the given rows array.
     *
     * @param array $rows The array of rows to check.
     * @param int $groupRowIndex The index to validate.
     *
     * @return bool True if the index points to a valid group row, false otherwise.
     */
    public static function isValidGroupRowIndex(array $rows, int $groupRowIndex): bool {
        return isset($rows[$groupRowIndex]) && static::isGroupRow($rows[$groupRowIndex]);
    }

    /**
     * Insert a row payload after the given row index.
     * 
     * @param array $rows The array of rows to modify.
     * @param int   $afterRowIndex The index after which to insert the new row payload.
     * @param array $rowPayload The row payload to insert.
     * 
     * @return void
     */
    public static function insertRowAfter(array &$rows, int $afterRowIndex, array $rowPayload): void {
        array_splice($rows, $afterRowIndex + 1, 0, [$rowPayload]);
    }

    /**
     * Insert a field payload into a row at the given position (max 3 fields per row).
     * 
     * @param array $rows     The array of rows to modify.
     * @param int   $rowIndex The index of the row to insert the field into.
     * @param int   $position The position within the row to insert the field (0-3).
     *
     * @return bool Returns false if the row does not exist or is already full.
     */
    public static function insertFieldIntoPayloadRow(array &$rows, int $rowIndex, int $position, array $payload): bool {
        if (!isset($rows[$rowIndex]) || !is_array($rows[$rowIndex]) || count($rows[$rowIndex]) >= 3) {
            return false;
        }

        array_splice($rows[$rowIndex], $position, 0, [$payload]);

        return true;
    }

    /**
     * Remove a field payload from a row and prune the row if it becomes empty.
     * 
     * @param array $rows       The array of rows to modify.
     * @param int   $rowIndex   The index of the row to remove the field from.
     * @param int   $fieldIndex The index of the field within the row to remove
     *
     * @return bool Returns false if the field does not exist at the given location.
     */
    public static function removeFieldFromPayloadRow(array &$rows, int $rowIndex, int $fieldIndex): bool {
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
     * @param array $rows       The array of rows to modify.
     * @param int   $rowIndex   The index of the row to extract the field from.
     * @param int   $fieldIndex The index of the field within the row to extract.
     *
     * @return array{field: array, rowRemoved: bool}|null
     */
    public static function extractFieldFromPayloadRows(array &$rows, int $rowIndex, int $fieldIndex): ?array {
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
            'field'      => $field,
            'rowRemoved' => $rowRemoved,
        ];
    }
}
