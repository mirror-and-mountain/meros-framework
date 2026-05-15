<?php

namespace MM\Meros\App\Toolbox\Helpers;

use Illuminate\Support\Arr;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\App\Fields\Repeater;

/**
 * Serializes form row/payload structures into normalised arrays for persistence.
 * Depends on a PayloadHydrator instance and the RowMutator helper.
 */
class FormStructureSerializer {
    public function __construct(private readonly PayloadHydrator $hydrator) {}

    /**
     * Build a normalised form structure array from the raw rows payload.
     *
     * @param array $rows The raw $rows property from a Tool component.
     * 
     * @return array
     */
    public function buildFormStructure(array $rows): array {
        $output = [];

        foreach (array_values($rows) as $rowIndex => $rowPayload) {
            if (RowMutator::isGroupRow($rowPayload)) {
                $groupPayload = $rowPayload['group'] ?? [];
                $group        = $this->hydrator->makeFieldGroupFromPayload($groupPayload);

                $groupRows = Arr::map(array_values($groupPayload['rows'] ?? []), function (array $row, int $groupRowIndex): array {
                    return [
                        'position' => $groupRowIndex,
                        'fields'   => $this->serializeRowFromPayload($row),
                    ];
                });

                $output[] = [
                    'position' => $rowIndex,
                    'type'     => 'group',
                    'group'    => [
                        'id'          => $groupPayload['id'] ?? null,
                        'handle'      => $groupPayload['handle'] ?? '',
                        'title'       => $groupPayload['title'] ?? '',
                        'description' => $groupPayload['description'] ?? '',
                        'definition'  => $group ? $group->toJson() : null,
                        'rows'        => $groupRows,
                    ],
                ];

                continue;
            }

            if (!is_array($rowPayload)) {
                continue;
            }

            $output[] = [
                'position' => $rowIndex,
                'type'     => 'fields',
                'fields'   => $this->serializeRowFromPayload($rowPayload),
            ];
        }

        return ['rows' => $output];
    }

    /**
     * Serialize a row payload into normalised field arrays.
     *
     * @param array $rowPayload
     * 
     * @return array
     */
    public function serializeRowFromPayload(array $rowPayload): array {
        $hydratedFields = $this->hydrator->hydratePayloadRows([array_values($rowPayload)]);
        $fields         = array_values($hydratedFields[0] ?? []);

        return Arr::map($fields, fn (Field $field, int $fieldIndex): array => $this->serializeField($field, $fieldIndex));
    }

    /**
     * Serialize a Field instance into a normalised array for persistence.
     *
     * @param Field $field
     * @param int   $position
     * 
     * @return array
     */
    public function serializeField(Field $field, int $position): array {
        $name = null;

        try {
            $name = $field->getName(false);
        } catch (\Throwable) {
            $name = null;
        }

        $payload = [
            'id'               => $field->getId(),
            'handle'           => $field->handle,
            'label'            => $field->getLabel(),
            'name'             => $name,
            'helpText'         => $field->getHelpText(),
            'helpTextPosition' => $field->getHelpTextPosition(),
            'value'            => $field->getValue(),
            'options'          => method_exists($field, 'getOptions') ? $field->getOptions() : null,
            'required'         => $field->isRequired(),
            'disabled'         => $field->isDisabled(),
            'width'            => $field->getWidth(),
            'variation'        => $field->getVariation(),
            'position'         => $position,
        ];

        if ($field instanceof Repeater) {
            $payload['fields'] = $field->getFields()
                ->values()
                ->map(fn (Field $childField, int $childIndex): array => $this->serializeField($childField, $childIndex))
                ->all();
            $payload['defaultRows'] = is_array($field->getValue()) ? $field->getValue() : [];
        }

        return $payload;
    }
}
