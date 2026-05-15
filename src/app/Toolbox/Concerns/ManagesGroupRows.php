<?php

namespace MM\Meros\App\Toolbox\Concerns;

use MM\Meros\App\Toolbox\Helpers\RowMutator;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Handles group container operations (add, remove, move) and all cross-group
 * field operations (add to group row, move between groups, move to/from top level).
 *
 * Expects the using class to have:
 *   - array $rows
 *   - PayloadHydrator $hydrator
 *   - methods: focusNewlyAddedFieldSettings()
 */
trait ManagesGroupRows {
    /**
     * Adds a field group container to the end of the canvas.
     * 
     * @param string $handle Optional field group handle to create the payload for.
     * 
     * @return void
     */
    public function addGroupToCanvas(string $handle = ''): void {
        $payload = $this->hydrator->makeFieldGroupPayload($handle);

        if (!$payload) {
            return;
        }

        $rows   = $this->rows;
        $rows[] = [
            '_type' => 'group',
            'group' => $payload,
        ];
        $this->rows = $rows;
    }

    /**
     * Inserts a field group container before any existing top-level row index.
     * 
     * @param int    $beforeRowIndex The index before which to insert the new group row.
     * @param string $handle         Optional field group handle to create the payload for.
     * 
     * @return void
     */
    public function addGroupBeforeRow(int $beforeRowIndex, string $handle = ''): void {
        $payload = $this->hydrator->makeFieldGroupPayload($handle);

        if (!$payload) {
            return;
        }

        $rows     = $this->rows;
        $insertAt = max(0, min($beforeRowIndex, count($rows)));

        array_splice($rows, $insertAt, 0, [[
            '_type' => 'group',
            'group' => $payload,
        ]]);

        $this->rows = $rows;
    }

    /**
     * Removes a group container from the canvas.
     * 
     * @param int $groupRowIndex The index of the group row to remove.
     * 
     * @return void
     */
    public function removeGroup(int $groupRowIndex): void {
        if (!isset($this->rows[$groupRowIndex]) || !RowMutator::isGroupRow($this->rows[$groupRowIndex])) {
            return;
        }

        $rows = $this->rows;
        array_splice($rows, $groupRowIndex, 1);
        $this->rows = $rows;
    }

    /**
     * Move a group row before another row index.
     * 
     * @param int $fromGroupRowIndex The index of the group row to move.
     * @param int $beforeRowIndex    The index before which to move the group row.
     * 
     * @return void
     */
    public function moveGroupRowBefore(int $fromGroupRowIndex, int $beforeRowIndex): void {
        if (!isset($this->rows[$fromGroupRowIndex]) || !RowMutator::isGroupRow($this->rows[$fromGroupRowIndex])) {
            return;
        }

        $targetIndex = max(0, min($beforeRowIndex, count($this->rows)));

        if ($fromGroupRowIndex === $targetIndex || $fromGroupRowIndex + 1 === $targetIndex) {
            return;
        }

        $rows = $this->rows;
        [$groupRow] = array_splice($rows, $fromGroupRowIndex, 1);
        $insertAt   = $targetIndex > $fromGroupRowIndex ? $targetIndex - 1 : $targetIndex;
        array_splice($rows, $insertAt, 0, [$groupRow]);
        $this->rows = $rows;
    }

    /**
     * Move a group row to the end of the top-level rows list.
     * 
     * @param int $fromGroupRowIndex The index of the group row to move.
     * 
     * @return void
     */
    public function moveGroupRowToEnd(int $fromGroupRowIndex): void {
        if (!isset($this->rows[$fromGroupRowIndex]) || !RowMutator::isGroupRow($this->rows[$fromGroupRowIndex])) {
            return;
        }

        $rows = $this->rows;
        [$groupRow] = array_splice($rows, $fromGroupRowIndex, 1);
        $rows[]     = $groupRow;
        $this->rows = $rows;
    }

    /**
     * Adds a field to a new row inside a group container.
     * 
     * @param int    $groupRowIndex The index of the group row to add the field to.
     * @param int    $afterRowIndex The index after which to add the new row.
     * @param string $handle        The handle of the field to add.
     * 
     * @return void
     */
    public function addFieldToGroupNewRow(int $groupRowIndex, int $afterRowIndex, string $handle): void {
        $payload = $this->hydrator->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows   = $rows[$groupRowIndex]['group']['rows'] ?? [];
        $newRowIndex = max(0, min($afterRowIndex + 1, count($groupRows)));
        RowMutator::insertRowAfter($groupRows, $afterRowIndex, [$payload]);
        $rows[$groupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $newRowIndex,
            'fieldIndex'    => 0,
        ]);
    }

    /**
     * Inserts a field into an existing row inside a group (max 3 fields per row).
     * 
     * @param int    $groupRowIndex The index of the group row to add the field to.
     * @param int    $rowIndex      The index of the row within the group to insert the field into.
     * @param int    $position      The position within the row to insert the field (0-3).
     * @param string $handle        The handle of the field to add.
     * 
     * @return void
     */
    public function insertFieldIntoGroupRow(int $groupRowIndex, int $rowIndex, int $position, string $handle): void {
        $payload = $this->hydrator->makeFieldPayload($handle);

        if (!$payload) {
            return;
        }

        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $targetPosition = max(0, min($position, count($rows[$groupRowIndex]['group']['rows'][$rowIndex] ?? [])));

        if (!RowMutator::insertFieldIntoPayloadRow($rows[$groupRowIndex]['group']['rows'], $rowIndex, $position, $payload)) {
            return;
        }

        $this->rows = $rows;

        $this->focusNewlyAddedFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $targetPosition,
        ]);
    }

    /**
     * Remove a field from a group row and remove empty rows.
     * 
     * @param int $groupRowIndex The index of the group row to remove the field from.
     * @param int $rowIndex      The index of the row within the group to remove the field from.
     * @param int $fieldIndex    The index of the field within the row to remove.
     * 
     * @return void
     */
    public function removeFieldFromGroup(int $groupRowIndex, int $rowIndex, int $fieldIndex): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        if (!RowMutator::removeFieldFromPayloadRow($rows[$groupRowIndex]['group']['rows'], $rowIndex, $fieldIndex)) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Relocate an existing field within a group's rows.
     * 
     * @param int $groupRowIndex The index of the group row containing the field.
     * @param int $fromRowIndex  The index of the row within the group where the field currently resides.
     * @param int $fromFieldIndex The index of the field within the row to move.
     * @param int $toRowIndex    The index of the row within the group to move the field to.
     * @param int $toPosition    The position within the target row to move the field to.
     * 
     * @return void
     */
    public function relocateFieldInGroup(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
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
     * 
     * @param int $groupRowIndex  The index of the group row containing the field.
     * @param int $fromRowIndex   The index of the row within the group where the field currently resides.
     * @param int $fromFieldIndex The index of the field within the row to move.
     * @param int $afterRowIndex  The index after which to move the field in a new row.
     * 
     * @return void
     */
    public function moveFieldToGroupNewRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $afterRowIndex): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
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

        RowMutator::insertRowAfter($groupRows, $afterRowIndex, [$field]);
        $this->rows = $rows;
    }

    /**
     * Move an existing top-level field into a row inside a group.
     * 
     * @param int $fromRowIndex   The index of the top-level row containing the field to move.
     * @param int $fromFieldIndex The index of the field within the top-level row to move.
     * @param int $groupRowIndex  The index of the group row to move the field into.
     * @param int $toRowIndex     The index of the row within the group to move the field to.
     * @param int $toPosition     The position within the target row to move the field to.
     * 
     * @return void
     */
    public function moveFieldToGroupRow(int $fromRowIndex, int $fromFieldIndex, int $groupRowIndex, int $toRowIndex, int $toPosition): void {
        $rows   = $this->rows;
        $result = RowMutator::extractFieldFromPayloadRows($rows, $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if ($result['rowRemoved'] && $fromRowIndex < $groupRowIndex) {
            $groupRowIndex--;
        }

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        if (!RowMutator::insertFieldIntoPayloadRow($rows[$groupRowIndex]['group']['rows'], $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing top-level field into a new row inside a group.
     * 
     * @param int $fromRowIndex   The index of the top-level row containing the field to move.
     * @param int $fromFieldIndex The index of the field within the top-level row to move.
     * @param int $groupRowIndex  The index of the group row to move the field into.
     * @param int $afterRowIndex  The index after which to move the field in a new row.
     * 
     * @return void
     */
    public function moveFieldToGroupNewRowFromTopLevel(int $fromRowIndex, int $fromFieldIndex, int $groupRowIndex, int $afterRowIndex): void {
        $rows   = $this->rows;
        $result = RowMutator::extractFieldFromPayloadRows($rows, $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if ($result['rowRemoved'] && $fromRowIndex < $groupRowIndex) {
            $groupRowIndex--;
        }

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $groupRows = $rows[$groupRowIndex]['group']['rows'] ?? [];
        RowMutator::insertRowAfter($groupRows, $afterRowIndex, [$result['field']]);
        $rows[$groupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a top-level row.
     * 
     * @param int $groupRowIndex  The index of the group row containing the field.
     * @param int $fromRowIndex   The index of the row within the group where the field currently resides.
     * @param int $fromFieldIndex The index of the field within the row to move.
     * @param int $toRowIndex     The index of the top-level row to move the field to.
     * @param int $toPosition     The position within the target top-level row to move the field to.
     * 
     * @return void
     */
    public function moveFieldFromGroupToRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $toRowIndex, int $toPosition): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $result = RowMutator::extractFieldFromPayloadRows($rows[$groupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if (!RowMutator::insertFieldIntoPayloadRow($rows, $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a new top-level row.
     * 
     * @param int $groupRowIndex  The index of the group row containing the field.
     * @param int $fromRowIndex   The index of the row within the group where the field currently resides.
     * @param int $fromFieldIndex The index of the field within the row to move.
     * @param int $afterRowIndex  The index after which to move the field in a new top-level row.
     * 
     * @return void
     */
    public function moveFieldFromGroupToNewRow(int $groupRowIndex, int $fromRowIndex, int $fromFieldIndex, int $afterRowIndex): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return;
        }

        $result = RowMutator::extractFieldFromPayloadRows($rows[$groupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        RowMutator::insertRowAfter($rows, $afterRowIndex, [$result['field']]);
        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a row in another group.
     * 
     * @param int $fromGroupRowIndex The index of the group row containing the field to move.
     * @param int $fromRowIndex      The index of the row within the source group where the field currently resides.
     * @param int $fromFieldIndex    The index of the field within the source row to move.
     * @param int $toGroupRowIndex   The index of the group row to move the field into.
     * @param int $toRowIndex        The index of the row within the target group to move the field to.
     * @param int $toPosition        The position within the target row to move the field to.
     * 
     * @return void
     */
    public function moveFieldBetweenGroups(
        int $fromGroupRowIndex, 
        int $fromRowIndex, 
        int $fromFieldIndex, 
        int $toGroupRowIndex, 
        int $toRowIndex, 
        int $toPosition
    ): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $fromGroupRowIndex) || !RowMutator::isValidGroupRowIndex($rows, $toGroupRowIndex)) {
            return;
        }

        $result = RowMutator::extractFieldFromPayloadRows($rows[$fromGroupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        if (!RowMutator::insertFieldIntoPayloadRow($rows[$toGroupRowIndex]['group']['rows'], $toRowIndex, $toPosition, $result['field'])) {
            return;
        }

        $this->rows = $rows;
    }

    /**
     * Move an existing grouped field into a new row in another group.
     * 
     * @param int $fromGroupRowIndex The index of the group row containing the field to move.
     * @param int $fromRowIndex      The index of the row within the source group where the field currently resides.
     * @param int $fromFieldIndex    The index of the field within the source row to move.
     * @param int $toGroupRowIndex   The index of the group row to move the field into.
     * @param int $afterRowIndex     The index after which to move the field in a new row within the target group.
     * 
     * @return void
     */
    public function moveFieldBetweenGroupsToNewRow(
        int $fromGroupRowIndex, 
        int $fromRowIndex, 
        int $fromFieldIndex, 
        int $toGroupRowIndex, 
        int $afterRowIndex
    ): void {
        $rows = $this->rows;

        if (!RowMutator::isValidGroupRowIndex($rows, $fromGroupRowIndex) || !RowMutator::isValidGroupRowIndex($rows, $toGroupRowIndex)) {
            return;
        }

        $result = RowMutator::extractFieldFromPayloadRows($rows[$fromGroupRowIndex]['group']['rows'], $fromRowIndex, $fromFieldIndex);

        if (!$result) {
            return;
        }

        $groupRows = $rows[$toGroupRowIndex]['group']['rows'] ?? [];
        RowMutator::insertRowAfter($groupRows, $afterRowIndex, [$result['field']]);
        $rows[$toGroupRowIndex]['group']['rows'] = $groupRows;
        $this->rows = $rows;
    }
}
