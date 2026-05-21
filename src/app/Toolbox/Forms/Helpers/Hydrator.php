<?php

namespace MM\Meros\App\Toolbox\Forms\Helpers;

use MM\Meros\Services\Contracts\Elements\Field;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;

class Hydrator {
    /**
     * Hydrates an array of row payloads with Field instances.
     *
     * @param array $rowPayloads
     * @param array $fieldTypes
     *
     * @return array
     */
    public static function hydrateRowPayloads(array $rowPayloads, array $fieldTypes): array {
        foreach ($rowPayloads as $index => $rowPayload) {
            if (!is_array($rowPayload)) {
                continue;
            }

            if (self::isGroupRow($rowPayload)) {
                $rowPayloads[$index] = [
                    '_type' => 'group',
                    'group' => self::hydrateGroupPayload($rowPayload['group'], $fieldTypes)
                ];
                continue;
            }

            $rowPayloads[$index] = [
                '_type'  => 'fields',
                'fields' => self::hydrateFieldPayloads($rowPayload['fields'] ?? [], $fieldTypes)
            ];
        }

        return $rowPayloads;
    }

    /**
     * Hydrates a group payload with Field instances.
     *
     * @param array $groupPayload
     * @param array $fieldTypes
     *
     * @return array
     */
    private static function hydrateGroupPayload(array $groupPayload, array $fieldTypes): array {
        $groupRows = [];

        foreach ($groupPayload['rows'] ?? [] as $index => $groupRow) {
            $groupRows[$index]['fields'] = self::hydrateFieldPayloads($groupRow['fields'] ?? [], $fieldTypes);
        }

        $groupPayload['rows'] = $groupRows;

        return $groupPayload;
    }

    /**
     * Hydrates an array of field payloads with Field instances.
     *
     * @param array $fieldPayloads
     * @param array $fieldTypes
     *
     * @return array
     */
    private static function hydrateFieldPayloads(array $fieldPayloads, array $fieldTypes): array {
        foreach ($fieldPayloads as $index => $fieldPayload) {
            if (!is_array($fieldPayload)) {
                continue;
            }

            $handle = $fieldPayload['handle'] ?? null;

            if (!is_string($handle) || trim($handle) === '') {
                continue;
            }

            if ($handle === 'repeater') {
                $repeaterField = self::hydrateFieldPayload($fieldPayload, $fieldTypes);

                if ($repeaterField === null) {
                    $fieldPayloads[$index] = null;
                    continue;
                }

                $subFields = array_filter(
                    self::hydrateFieldPayloads($fieldPayload['fields'] ?? [], $fieldTypes),
                    fn($field): bool => $field instanceof Field,
                );

                if (method_exists($repeaterField, 'attach')) {
                    $repeaterField->attach($subFields);
                }

                $fieldPayloads[$index] = $repeaterField;
                continue;
            }

            $fieldPayloads[$index] = self::hydrateFieldPayload($fieldPayload, $fieldTypes);
        }

        return $fieldPayloads;
    }

    /**
     * Hydrates a field payload with a Field instance.
     *
     * @param array $fieldPayload
     * @param array $fieldTypes
     *
     * @return Field|null
     */
    public static function hydrateFieldPayload(array $fieldPayload, array $fieldTypes): ?Field {
        $handle = $fieldPayload['handle'] ?? null;
        $id     = $fieldPayload['properties']['id'] ?? null;

        // Make sure we have a valid handle and ID.
        if (!$handle || !$id) {
            return null;
        }

        return self::makeFieldInstance($handle, $fieldPayload['properties'] ?? [], $fieldTypes);
    }

    /**
     * Instantiates a Field instance by handle and properties.
     *
     * @param string $handle
     * @param array  $properties
     * @param array  $fieldTypes
     *
     * @return Field|null
     */
    private static function makeFieldInstance(string $handle, array $properties, array $fieldTypes): ?Field {
        $fieldType = $fieldTypes[$handle] ?? null;
        
        if (!$fieldType) {
            return null;
        }

        $fieldInstance = Fields::checkout(Framework::get())
            ->makeFrom($fieldType, $properties);

        return $fieldInstance;
    }

    /**
     * Helper to determine if a given row payload is a group row.
     *
     * @param array  $rowPayload
     * @param string $key
     *
     * @return boolean
     */
    public static function isGroupRow(array $rowPayload, string $key = '_type'): bool {
        return ($rowPayload[$key] ?? null ) === 'group' && is_array($rowPayload['group'] ?? null);
    }
}