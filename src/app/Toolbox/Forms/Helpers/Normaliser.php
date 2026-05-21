<?php 

namespace MM\Meros\App\Toolbox\Forms\Helpers;

use Illuminate\Support\Str;

class Normaliser {
    /**
     * Normalises a form schema's rows as compatible row payloads.
     *
     * @param array $rowsSchema
     *
     * @return array The normalised array of row payloads.
     */
    public static function normaliseRowPayloads(array $rowsSchema): array {
        $normalisedRows = [];

        foreach (array_values($rowsSchema) as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (Hydrator::isGroupRow($row, 'type')) {
                $normalisedRows[] = [
                    '_type' => 'group',
                    'group' => self::normaliseGroupPayload($row['group'])
                ];
                continue;
            }

            $normalisedRows[] = [
                '_type'  => 'fields',
                'fields' => self::normaliseFieldPayloads($row['fields'] ?? [])
            ];
        }

        return $normalisedRows;
    }

    /**
     * Normalises a group payload.
     *
     * @param array $group The group payload to normalise.
     *
     * @return array The normalised group payload.
     */
    private static function normaliseGroupPayload(array $group): array {
        $groupRows = [];

        foreach ($group['rows'] ?? [] as $index => $groupRow) {
            $groupRows[$index]['fields'] = self::normaliseFieldPayloads($groupRow['fields'] ?? []);
        }

        $normalisedGroup = [
            'id'          => $group['id'] ?? Str::uuid()->toString(),
            'handle'      => $group['handle'] ?? '',
            'title'       => $group['title'] ?? 'Untitled Section',
            'description' => $group['description'] ?? '',
            'rows'        => $groupRows
        ];

        return $normalisedGroup;
    }

    /**
     * Normalises an array of field payloads.
     *
     * @param array $fields The array of field payloads to normalise.
     *
     * @return array The normalised array of field payloads.
     */
    private static function normaliseFieldPayloads(array $fields): array {
        $normalisedFields = [];

        foreach (array_values($fields) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $handle = $field['handle'] ?? null;

            // Check we have a valid handle
            if (!is_string($handle) || trim($handle) === '') {
                continue;
            }

            // Make sure the field has a properties array.
            if (!isset($field['properties'])) {
                $field['properties'] = [];
            }

            // Make sure the field has an assigned ID.
            if (!isset($field['properties']['id']) || // Check ID is set
                !is_string($field['properties']['id']) || // Check ID is a string
                trim($field['properties']['id']) === '' // Check ID is not empty
            ) {
                $field['properties']['id'] = Str::uuid()->toString();
            }

            // Normalise repeater sub-fields if this is a repeater field.
            if ($handle === 'repeater') {
                if (empty($field['fields']) || !is_array($field['fields'])) {
                    // Create some default sub-fields
                    $field['fields'][] = [
                        'handle' => 'text',
                        'properties' => [
                            'name'  => 'text',
                            'label' => 'Text',
                            'id'    => Str::uuid()->toString()
                        ]
                    ];

                    $field['fields'][] = [
                        'handle' => 'number',
                        'properties' => [
                            'name'  => 'number',
                            'label' => 'Number',
                            'id'    => Str::uuid()->toString()
                        ]
                    ];

                    $field['fields'][] = [
                        'handle' => 'checkbox',
                        'properties' => [
                            'name'  => 'checkbox',
                            'label' => 'Checkbox',
                            'id'    => Str::uuid()->toString()
                        ]
                    ];
                }

                $field['fields'] = self::normaliseFieldPayloads($field['fields'] ?? []);
                $field['properties']['value'] = self::normaliseRepeaterValueRows($field['properties']['value'] ?? []);
            }

            $normalisedFields[] = $field;
        }

        return $normalisedFields;
    }

    /**
     * Normalises repeater value rows and ensures each row has a stable __rowKey.
     * 
     * @param mixed $value The value to normalise as repeater rows.
     * 
     * @return array The normalised repeater rows.
     */
    private static function normaliseRepeaterValueRows(mixed $value): array {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];

        foreach (array_values($value) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowKey = $row['__rowKey'] ?? null;

            if (!is_string($rowKey) || trim($rowKey) === '') {
                $row['__rowKey'] = Str::uuid()->toString();
            }

            $rows[] = $row;
        }

        return $rows;
    }
}