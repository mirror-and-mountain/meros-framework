<?php

namespace MM\Meros\App\Toolbox\Concerns;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Dispatches Livewire browser events to keep TomSelect instances in sync
 * with server-side field/repeater payload state.
 *
 * Expects the using class to have:
 *   - methods: getFieldPayloadAt(), dispatch()
 */
trait DispatchesTomSelectEvents {
    /**
     * Dispatch a browser event to sync TomSelect for a specific field location.
     * Keeps wire:ignore-managed select DOM in sync with settings changes.
     *
     * @param array<string, int|null> $location
     * 
     * @return void
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

        $rowIndex      = $location['rowIndex'] ?? null;
        $fieldIndex    = $location['fieldIndex'] ?? null;
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
     * @param array<string, int|null> $location    Location of the repeater field.
     * @param int                     $columnIndex Index of the column that changed.
     * 
     * @return void
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
        $handle        = (string) ($columnPayload['handle'] ?? '');

        if (!in_array($handle, ['select', 'multi_select'], true)) {
            return;
        }

        $rowIndex      = $location['rowIndex'] ?? null;
        $fieldIndex    = $location['fieldIndex'] ?? null;
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
     * @param array<string, int|null> $location  Location of the repeater field.
     * @param int                     $rowIndex  Index of the row that changed.
     * @param string                  $fieldName Name of the field that changed.
     * @param mixed                   $value     New value.
     * 
     * @return void
     */
    private function dispatchTomSelectRepeaterRowValueSyncForLocation(
        array $location, int $rowIndex, string $fieldName, mixed $value
    ): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload) || ($payload['handle'] ?? null) !== 'repeater') {
            return;
        }

        $columns     = array_values(array_filter($payload['fields'] ?? [], 'is_array'));
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

        $locRowIndex   = $location['rowIndex'] ?? null;
        $locFieldIndex = $location['fieldIndex'] ?? null;
        $groupRowIndex = $location['groupRowIndex'] ?? null;

        if (!is_int($locRowIndex) || !is_int($locFieldIndex)) {
            return;
        }

        $repeaterLocationKey = is_int($groupRowIndex)
            ? sprintf('group-%d-%d-%d', $groupRowIndex, $locRowIndex, $locFieldIndex)
            : sprintf('%d-%d', $locRowIndex, $locFieldIndex);

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
}
