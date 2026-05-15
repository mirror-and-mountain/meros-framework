<?php

namespace MM\Meros\App\Toolbox\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\FieldGroup;
use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;
use MM\Meros\Facades\Framework;

/**
 * Hydrates payload arrays into Field and FieldGroup instances.
 * Designed to be reusable by any Tool component that works with form payloads.
 */
class PayloadHydrator {
    /**
     * Registered field types keyed by handle.
     *
     * @var array<string, class-string>
     */
    private array $fieldTypes;

    /**
     * Registered field group types keyed by handle.
     *
     * @var array<string, array>
     */
    private array $fieldGroups;

    public function __construct(array $fieldTypes, array $fieldGroups) {
        $this->fieldTypes  = $fieldTypes;
        $this->fieldGroups = $fieldGroups;
    }

    /**
     * Create a payload array for a new field instance based on the given field type handle.
     * 
     * @param string $handle The field type handle to create the payload for.
     *
     * @return array|null
     */
    public function makeFieldPayload(string $handle): ?array {
        $fieldType = $this->fieldTypes[$handle] ?? null;

        if (!$fieldType) {
            return null;
        }

        $payload = [
            'id'     => Str::uuid()->toString(),
            'handle' => $handle,
            'style'  => 'nice',
        ];

        if ($handle === 'repeater') {
            $defaultTextField = $this->makeFieldPayload('text');

            $payload['fields'] = is_array($defaultTextField) ? [$defaultTextField] : [];
            $payload['defaultRows'] = [
                ['text' => null],
            ];
            $payload['value'] = $payload['defaultRows'];
        }

        return $payload;
    }

    /**
     * Create a payload array for a field group container.
     * 
     * @param string $handle The field group handle to create the payload for.
     *
     * @return array|null
     */
    public function makeFieldGroupPayload(string $handle = ''): ?array {
        $title       = 'Untitled Section';
        $description = '';
        $rows        = [];

        if ($handle !== '') {
            if (!isset($this->fieldGroups[$handle])) {
                return null;
            }

            $title = $this->fieldGroups[$handle]['label'] ?? $title;

            try {
                $group         = FieldGroupsRegister::checkout(Framework::get())->makeFrom($handle);
                $defaultFields = $group->getFields()
                    ->map(function ($field) {
                        if ($field instanceof Field) {
                            return $field->handle;
                        }

                        return is_string($field) ? $field : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($defaultFields)) {
                    $buffer = [];

                    foreach ($defaultFields as $fieldHandle) {
                        $fieldPayload = $this->makeFieldPayload($fieldHandle);

                        if (!$fieldPayload) {
                            continue;
                        }

                        $buffer[] = $fieldPayload;

                        if (count($buffer) === 3) {
                            $rows[]  = $buffer;
                            $buffer  = [];
                        }
                    }

                    if (!empty($buffer)) {
                        $rows[] = $buffer;
                    }
                }
            } catch (\Throwable) {
                // Keep payload creation resilient; fall back to an empty group.
            }
        }

        return [
            'id'          => Str::uuid()->toString(),
            'handle'      => $handle,
            'title'       => $title,
            'description' => $description,
            'rows'        => $rows,
        ];
    }

    /**
     * Create a Field instance from a payload array.
     * 
     * @param array $payload The payload array to create the Field instance from.
     *
     * @return Field|null
     */
    public function makeFieldFromPayload(array $payload): ?Field {
        $handle = $payload['handle'] ?? null;
        $id     = $payload['id'] ?? null;

        if (!$handle || !$id) {
            return null;
        }

        $fieldType = $this->fieldTypes[$handle] ?? null;

        if (!$fieldType) {
            return null;
        }

        $fieldInstance = Fields::checkout(Framework::get())->makeFrom($fieldType);

        $fieldInstance->name((string) ($payload['name'] ?? $handle));
        $fieldInstance->id($id);

        if (!empty($payload['label'])) {
            $fieldInstance->label($payload['label']);
        }

        if (array_key_exists('helpText', $payload)) {
            $fieldInstance->helpText(
                (string) ($payload['helpText'] ?? ''),
                (string) ($payload['helpTextPosition'] ?? 'bottom')
            );
        }

        if (array_key_exists('value', $payload)) {
            $fieldInstance->value($payload['value']);
        }

        if (array_key_exists('required', $payload)) {
            $fieldInstance->required((bool) $payload['required']);
        }

        if (array_key_exists('disabled', $payload)) {
            $fieldInstance->disabled((bool) $payload['disabled']);
        }

        if (!empty($payload['width'])) {
            $fieldInstance->width((string) $payload['width']);
        }

        if (!empty($payload['style'])) {
            $fieldInstance->style($payload['style']);
        }

        if (!empty($payload['placeholder']) && method_exists($fieldInstance, 'placeholder')) {
            $fieldInstance->placeholder((string) $payload['placeholder']);
        }

        if (array_key_exists('options', $payload) && is_array($payload['options']) && method_exists($fieldInstance, 'options')) {
            $fieldInstance->options($payload['options']);
        }

        if (array_key_exists('rows', $payload) && method_exists($fieldInstance, 'rows')) {
            $fieldInstance->rows(max(1, (int) $payload['rows']));
        }

        if ($handle === 'multi_select') {
            if (method_exists($fieldInstance, 'advanced')) {
                $fieldInstance->advanced(true);
            }
        } elseif ($handle === 'select') {
            if (array_key_exists('advanced', $payload) && method_exists($fieldInstance, 'advanced')) {
                $fieldInstance->advanced((bool) $payload['advanced']);
            }
        }

        if (array_key_exists('allowAdd', $payload) && method_exists($fieldInstance, 'allowAdd')) {
            $fieldInstance->allowAdd((bool) $payload['allowAdd']);
        }

        if ($fieldInstance instanceof Repeater) {
            $this->hydrateRepeaterField($fieldInstance, $payload);
        }

        return $fieldInstance;
    }

    /**
     * Hydrate repeater-specific child fields and default rows from payload.
     * 
     * @param Repeater $fieldInstance The Repeater field instance to hydrate.
     * @param array    $payload       The payload array containing repeater configuration.
     * 
     * @return void
     */
    public function hydrateRepeaterField(Repeater $fieldInstance, array $payload): void {
        $childPayloads = array_values(array_filter($payload['fields'] ?? [], 'is_array'));

        foreach ($childPayloads as $childPayload) {
            $childField = $this->makeFieldFromPayload($childPayload);

            if ($childField) {
                $fieldInstance->attach($childField);
            }
        }

        if (array_key_exists('defaultRows', $payload)) {
            $fieldInstance->value(is_array($payload['defaultRows']) ? $payload['defaultRows'] : []);
        }
    }

    /**
     * Create a FieldGroup instance from a group payload.
     * 
     * @param array $payload The payload array to create the FieldGroup instance from.
     *
     * @return FieldGroup|null
     */
    public function makeFieldGroupFromPayload(array $payload): ?FieldGroup {
        $handle = $payload['handle'] ?? '';

        try {
            if ($handle !== '' && isset($this->fieldGroups[$handle])) {
                $group = FieldGroupsRegister::checkout(Framework::get())->makeFrom($handle);
            } else {
                $group = new FieldGroup(Framework::get(), []);
            }

            if ($handle !== '') {
                $group->handle($handle);
            }

            $group->title($payload['title'] ?? 'Untitled Section');
            $group->description($payload['description'] ?? '');

            return $group;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hydrate payload rows into arrays of Field instances.
     *
     * @param array $rows
     * 
     * @return array<int, array<int, Field>>
     */
    public function hydratePayloadRows(array $rows): array {
        return Arr::map($rows, function (array $row): array {
            $fields = Arr::map($row, function ($payload) {
                if (!is_array($payload)) {
                    return null;
                }

                return $this->makeFieldFromPayload($payload);
            });

            return array_values(Arr::where($fields, fn ($field) => !is_null($field)));
        });
    }
}
