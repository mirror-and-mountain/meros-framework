<?php

namespace MM\Meros\Support;

/**
 * Helper class for generating explicit-empty marker names for form field inputs.
 */
final class FormFieldName {
    /**
     * Returns the explicit-empty marker name for a field input name.
     *
     * Examples:
     * - options[] => options[__empty]
     * - meta[items][] => meta[items][__empty]
     * - repeater => repeater[__empty]
     */
    public static function emptyMarkerName(string $fieldName): string {
        $fieldName = trim($fieldName);

        if ($fieldName === '') {
            return '__empty';
        }

        if (str_ends_with($fieldName, '[__empty]')) {
            return $fieldName;
        }

        if (str_ends_with($fieldName, '[]')) {
            return substr($fieldName, 0, -2) . '[__empty]';
        }

        return $fieldName . '[__empty]';
    }
}