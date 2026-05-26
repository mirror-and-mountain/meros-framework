<?php

namespace MM\Meros\App\Toolbox\Forms\Helpers;

use MM\Meros\App\Toolbox\Forms\Builder;
use MM\Meros\Services\Contracts\Elements\Field;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;

class Hydrator {
    /**
     * An array of valid field types.
     *
     * @var array
     */
    private array $fieldTypes = [];

    private ?Builder $builder = null;

    private function __construct(array $fieldTypes, ?Builder $builder = null) {
        $this->fieldTypes = $fieldTypes;
        $this->builder    = $builder;
    }

    public static function make(array $fieldTypes, ?Builder $builder = null): self {
        return new self($fieldTypes, $builder);
    }

    /**
     * Hydrates an array of row payloads with Field instances.
     *
     * @param array $rowPayloads
     *
     * @return array
     */
    public function hydrateRowPayloads(array $rowPayloads): array {
        foreach ($rowPayloads as $index => $rowPayload) {
            if (!is_array($rowPayload)) {
                continue;
            }

            if (Utilities::isGroupRow($rowPayload)) {
                $rowPayloads[$index] = [
                    '_type' => 'group',
                    'group' => $this->hydrateGroupPayload($rowPayload['group'])
                ];
                continue;
            }

            $rowPayloads[$index] = [
                '_type'  => 'fields',
                'fields' => $this->hydrateFieldPayloads($rowPayload['fields'] ?? [])
            ];
        }

        return $rowPayloads;
    }

    /**
     * Hydrates a group payload with Field instances.
     *
     * @param array $groupPayload
     *
     * @return array
     */
    private function hydrateGroupPayload(array $groupPayload): array {
        $groupRows = [];

        foreach ($groupPayload['rows'] ?? [] as $index => $groupRow) {
            $groupRows[$index]['fields'] = $this->hydrateFieldPayloads($groupRow['fields'] ?? []);
        }

        $groupPayload['rows'] = $groupRows;

        return $groupPayload;
    }

    /**
     * Hydrates an array of field payloads with Field instances.
     *
     * @param array $fieldPayloads
     *
     * @return array
     */
    private function hydrateFieldPayloads(array $fieldPayloads): array {
        foreach ($fieldPayloads as $index => $fieldPayload) {
            if (!is_array($fieldPayload)) {
                continue;
            }

            $handle = $fieldPayload['handle'] ?? null;

            if (!is_string($handle) || trim($handle) === '') {
                continue;
            }

            $fieldPayloads[$index] = $this->hydrateFieldPayload($fieldPayload);
        }

        return $fieldPayloads;
    }

    /**
     * Hydrates a field payload with a Field instance.
     *
     * @param array $fieldPayload
     *
     * @return Field|null
     */
    public function hydrateFieldPayload(array $fieldPayload): ?Field {
        $handle = $fieldPayload['handle'] ?? null;
        $id     = $fieldPayload['properties']['id'] ?? null;

        // Make sure we have a valid handle and ID.
        if (!$handle || !$id) {
            return null;
        }

        $instance = $this->makeFieldInstance($handle, $fieldPayload['properties'] ?? []);

        if ($fieldPayload['handle'] === 'repeater') {
            $fields = $fieldPayload['fields'] ?? [];
            $hydratedSubFields = [];

            foreach ($fields as $subFieldPayload) {
                if (!is_array($subFieldPayload)) {
                    continue;
                }

                $hydratedSubField = $this->hydrateFieldPayload($subFieldPayload);

                if ($hydratedSubField !== null) {
                    $hydratedSubFields[] = $hydratedSubField;
                }
            }

            if (method_exists($instance, 'refresh')) {
                $instance->refresh($hydratedSubFields);
            }
        }

        if ($this->builder) {
            $this->builder->addElement($instance);
        }

        return $instance;
    }

    /**
     * Instantiates a Field instance by handle and properties.
     *
     * @param string $handle
     * @param array  $properties
     *
     * @return Field|null
     */
    private function makeFieldInstance(string $handle, array $properties): ?Field {
        $fieldType = $this->fieldTypes[$handle] ?? null;
        
        if (!$fieldType) {
            return null;
        }

        $fieldInstance = Fields::checkout(Framework::get())
            ->makeFrom($fieldType, $properties);

        return $fieldInstance;
    }
}