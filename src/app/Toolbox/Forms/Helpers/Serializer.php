<?php 

namespace MM\Meros\App\Toolbox\Forms\Helpers;

use Illuminate\Support\Str;

class Serializer {
    /**
     * Serializes an array of row payloads into a format suitable for storage.
     *
     * @param array $rowPayloads
     * @param array $fieldTypes
     * 
     * @return array
     */
    public static function serializeFormSchema(array $rowPayloads, array $fieldTypes): array {
        $serializedRows = [];

        foreach ($rowPayloads as $rowPayload) {
            if (!is_array($rowPayload)) {
                continue;
            }

            if (Hydrator::isGroupRow($rowPayload)) {
                $serializedRows[] = [
                    'type'  => 'group',
                    'group' => self::serializeGroupPayload($rowPayload['group'] ?? [], $fieldTypes)
                ];
                continue;
            }

            $serializedRows[] = [
                'type'   => 'fields',
                'fields' => self::serializeFieldPayloads($rowPayload['fields'] ?? [], $fieldTypes)
            ];
        }

        return $serializedRows;
    }

    /**
     * Serializes a group payload into a format suitable for storage.
     *
     * @param array $groupPayload
     * @param array $fieldTypes
     *
     * @return array
     */
    private static function serializeGroupPayload(array $groupPayload, array $fieldTypes): array {
        $serializedGroup = [
            'id'          => $groupPayload['id'] ?? Str::uuid()->toString(),
            'handle'      => $groupPayload['handle'] ?? '',
            'title'       => $groupPayload['title'] ?? 'Untitled Section',
            'description' => $groupPayload['description'] ?? '',
            'rows'        => []
        ];

        foreach ($groupPayload['rows'] ?? [] as $groupRow) {
            $serializedGroup['rows'][] = [
                'fields' => self::serializeFieldPayloads($groupRow['fields'] ?? [], $fieldTypes)
            ];
        }

        return $serializedGroup;
    }

    /**
     * Serializes an array of field payloads into a format suitable for storage.
     *
     * @param array $fieldPayloads
     * @param array $fieldTypes
     *
     * @return array
     */
    private static function serializeFieldPayloads(array $fieldPayloads, array $fieldTypes): array {
        $serializedFields = [];

        foreach ($fieldPayloads as $fieldPayload) {
            if (!is_array($fieldPayload)) {
                continue;
            }

            $serializedField = self::serializeFieldPayload($fieldPayload, $fieldTypes);

            if (!is_array($serializedField)) {
                continue;
            }

            $serializedFields[] = $serializedField;
        }

        return $serializedFields;
    }

    /**
     * Serializes a single field payload and recursively hydrates repeater sub-fields.
     */
    private static function serializeFieldPayload(array $fieldPayload, array $fieldTypes): ?array {
        $handle = $fieldPayload['handle'] ?? null;

        if (!is_string($handle) || trim($handle) === '') {
            return null;
        }

        if ($handle === 'repeater') {
            $repeaterInstance = Hydrator::hydrateFieldPayload($fieldPayload, $fieldTypes);

            if ($repeaterInstance === null) {
                return null;
            }

            $hydratedSubFields = [];

            foreach ($fieldPayload['fields'] ?? [] as $subFieldPayload) {
                if (!is_array($subFieldPayload)) {
                    continue;
                }

                $serializedSubField = self::serializeFieldPayload($subFieldPayload, $fieldTypes);

                if (!is_array($serializedSubField)) {
                    continue;
                }

                $subFieldInstance = Hydrator::hydrateFieldPayload($serializedSubField, $fieldTypes);

                if ($subFieldInstance !== null) {
                    $hydratedSubFields[] = $subFieldInstance;
                }
            }

            if (method_exists($repeaterInstance, 'attach')) {
                $repeaterInstance->attach($hydratedSubFields);
            }

            return $repeaterInstance->toJson();
        }

        $fieldInstance = Hydrator::hydrateFieldPayload($fieldPayload, $fieldTypes);

        if ($fieldInstance === null) {
            return null;
        }

        return $fieldInstance->toJson();
    }
}