<?php 

namespace MM\Meros\App\Toolbox\Forms\Helpers;

use Illuminate\Support\Str;

class Serializer {
    /**
     * The Hydrator instance used to resolve field types during serialization.
     *
     * @var Hydrator
     */
    private Hydrator $hydrator;

    private function __construct(Hydrator $hydrator) {
        $this->hydrator   = $hydrator;
    }

    public static function make(Hydrator $hydrator): self {
        return new self($hydrator);
     }

    /**
     * Serializes an array of row payloads into a format suitable for storage.
     *
     * @param array $rowPayloads
     * 
     * @return array
     */
    public function serializeFormSchema(array $rowPayloads): array {
        $serializedRows = [];

        foreach ($rowPayloads as $rowPayload) {
            if (!is_array($rowPayload)) {
                continue;
            }

            if (Utilities::isGroupRow($rowPayload)) {
                $serializedRows[] = [
                    'type'  => 'group',
                    'group' => $this->serializeGroupPayload($rowPayload['group'] ?? [])
                ];
                continue;
            }

            $serializedRows[] = [
                'type'   => 'fields',
                'fields' => $this->serializeFieldPayloads($rowPayload['fields'] ?? [])
            ];
        }

        return $serializedRows;
    }

    /**
     * Serializes a group payload into a format suitable for storage.
     *
     * @param array $groupPayload
     *
     * @return array
     */
    private function serializeGroupPayload(array $groupPayload): array {
        $serializedGroup = [
            'id'          => $groupPayload['id'] ?? 'field_' . Str::substr(Str::uuid()->toString(), 0, 8),
            'handle'      => $groupPayload['handle'] ?? '',
            'title'       => $groupPayload['title'] ?? 'Untitled Section',
            'description' => $groupPayload['description'] ?? '',
            'rows'        => []
        ];

        foreach ($groupPayload['rows'] ?? [] as $groupRow) {
            $serializedGroup['rows'][] = [
                'fields' => $this->serializeFieldPayloads($groupRow['fields'] ?? [])
            ];
        }

        return $serializedGroup;
    }

    /**
     * Serializes an array of field payloads into a format suitable for storage.
     *
     * @param array $fieldPayloads
     *
     * @return array
     */
    private function serializeFieldPayloads(array $fieldPayloads): array {
        $serializedFields = [];

        foreach ($fieldPayloads as $fieldPayload) {
            if (!is_array($fieldPayload)) {
                continue;
            }

            $serializedField = $this->serializeFieldPayload($fieldPayload);

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
    private function serializeFieldPayload(array $fieldPayload): ?array {
        $handle = $fieldPayload['handle'] ?? null;

        if (!is_string($handle) || trim($handle) === '') {
            return null;
        }

        if ($handle === 'repeater') {
            $repeaterInstance = $this->hydrator->hydrateFieldPayload($fieldPayload);

            if ($repeaterInstance === null) {
                return null;
            }

            $hydratedSubFields = [];

            foreach ($fieldPayload['fields'] ?? [] as $subFieldPayload) {
                if (!is_array($subFieldPayload)) {
                    continue;
                }

                $serializedSubField = $this->serializeFieldPayload($subFieldPayload);

                if (!is_array($serializedSubField)) {
                    continue;
                }

                $subFieldInstance = $this->hydrator->hydrateFieldPayload($serializedSubField);

                if ($subFieldInstance !== null) {
                    $hydratedSubFields[] = $subFieldInstance;
                }
            }

            if (method_exists($repeaterInstance, 'refresh')) {
                $repeaterInstance->refresh($hydratedSubFields);
            }

            return $repeaterInstance->toJson();
        }

        $fieldInstance = $this->hydrator->hydrateFieldPayload($fieldPayload);

        if ($fieldInstance === null) {
            return null;
        }

        return $fieldInstance->toJson();
    }
}