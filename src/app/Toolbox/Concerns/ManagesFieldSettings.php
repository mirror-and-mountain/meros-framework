<?php

namespace MM\Meros\App\Toolbox\Concerns;

use Illuminate\Support\Str;

/**
 * @mixin \MM\Meros\App\Toolbox\FormBuilder
 *
 * Manages the field settings panel: open/close, update, validation, and view model.
 * Also owns the internal selectFieldSettings / focusNewlyAddedFieldSettings helpers.
 *
 * Expects the using class to have:
 *   - array  $rows
 *   - array|null $activeFieldSettings
 *   - array|null $activeRepeater
 *   - PayloadHydrator $hydrator
 *   - methods: closeRepeaterBuilder(), mutateFieldPayloadAt(),
 *              dispatchTomSelectFieldSyncForLocation(), getFieldPayloadAt()
 */
trait ManagesFieldSettings {
    /**
     * Open the settings panel for a top-level field.
     * 
     * @param int $rowIndex   The index of the row containing the field to edit.
     * @param int $fieldIndex The index of the field within the row to edit.
     * 
     * @return void
     */
    public function editField(int $rowIndex, int $fieldIndex): void {
        $this->selectFieldSettings([
            'groupRowIndex' => null,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ]);
    }

    /**
     * Open the settings panel for a grouped field.
     * 
     * @param int $groupRowIndex The index of the group row containing the field to edit.
     * @param int $rowIndex      The index of the row within the group containing the field to edit.
     * @param int $fieldIndex    The index of the field within the row to edit.
     * 
     * @return void
     */
    public function editGroupField(int $groupRowIndex, int $rowIndex, int $fieldIndex): void {
        $this->selectFieldSettings([
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ]);
    }

    /**
     * Close the generic field settings panel.
     * 
     * @return void
     */
    public function closeFieldSettings(): void {
        $this->activeFieldSettings = null;
    }

    /**
     * Update the default value of a canvas field at the given location.
     * Called when the user interacts with a field directly on the canvas.
     * 
     * @param int|null $groupRowIndex The index of the group row containing the field, if applicable.
     * @param int|null $rowIndex      The index of the row containing the field.
     * @param int|null $fieldIndex    The index of the field within the row.
     * @param mixed    $value         The new value to set for the field.
     * 
     * @return void
     */
    public function updateFieldDefaultValue(?int $groupRowIndex, ?int $rowIndex, ?int $fieldIndex, mixed $value): void {
        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return;
        }

        $location = [
            'groupRowIndex' => $groupRowIndex,
            'rowIndex'      => $rowIndex,
            'fieldIndex'    => $fieldIndex,
        ];

        $this->mutateFieldPayloadAt($location, function (array $payload) use ($value): array {
            $payload['value'] = $value;
            return $payload;
        });
    }

    /**
     * Update a setting on the active field payload.
     * 
     * @param string $key   The key of the setting to update.
     * @param mixed  $value The new value for the setting.
     * 
     * @return void
     */
    public function updateActiveFieldSetting(string $key, mixed $value): void {
        if (!$this->activeFieldSettings) {
            return;
        }

        $updated = $this->mutateFieldPayloadAt($this->activeFieldSettings, function (array $payload) use ($key, $value): array {
            if (($payload['handle'] ?? null) === 'repeater') {
                return $payload;
            }

            if ($key === 'optionsText') {
                $payload['options'] = $this->parseOptionsText((string) $value);
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if (in_array($key, ['required', 'disabled'], true)) {
                $payload[$key] = (bool) $value;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'rows') {
                $payload['rows'] = max(1, (int) $value);
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'id') {
                $nextId = trim((string) $value);

                if ($nextId !== '') {
                    $payload['id'] = $nextId;
                }

                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if ($key === 'name') {
                $nextName      = trim((string) $value);
                $payload['name'] = $nextName === '' ? (string) ($payload['handle'] ?? 'field') : $nextName;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            if (in_array($key, ['label', 'helpText', 'helpTextPosition', 'width', 'placeholder', 'advanced', 'allowAdd'], true)) {
                $payload[$key] = is_string($value) ? $value : (string) $value;
                $payload['_fieldVersion'] = ($payload['_fieldVersion'] ?? 0) + 1;
                return $payload;
            }

            return $payload;
        });

        if ($updated) {
            $this->dispatchTomSelectFieldSyncForLocation($this->activeFieldSettings);
        }
    }

    /**
     * Parse newline-delimited options text into value => label pairs.
     * Each line can be in the format "value|label" or just "label" (in which case the value is a slug of the label).
     * 
     * @param string $text The raw options text to parse.
     * 
     * @return array An associative array of options where keys are option values and values are option labels.
     */
    private function parseOptionsText(string $text): array {
        $lines   = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $options = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$value, $label] = array_map('trim', explode('|', $line, 2));
            } else {
                $label = $line;
                $value = Str::slug($line);
            }

            if ($value === '') {
                $value = Str::slug($label);
            }

            $options[$value] = $label;
        }

        return $options;
    }

    /**
     * Build a view model for the active generic field settings panel.
     * 
     * @return array|null An associative array containing the field, its payload, and metadata for rendering the settings panel, or null if no valid field is selected.
     */
    private function getActiveFieldSettingsViewModel(): ?array{
        if (!$this->activeFieldSettings) {
            return null;
        }

        $payload = $this->getFieldPayloadAt($this->activeFieldSettings);

        if (!is_array($payload) || ($payload['handle'] ?? null) === 'repeater') {
            return null;
        }

        $field = $this->hydrator->makeFieldFromPayload($payload);

        if (!$field) {
            return null;
        }

        $handle             = (string) ($payload['handle'] ?? '');
        $optionHandles      = ['select', 'multi_select', 'radio', 'checkboxes'];
        $placeholderHandles = ['text', 'email', 'url', 'number', 'password', 'date', 'time'];

        $options     = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $optionsText = collect($options)
            ->map(fn ($label, $value) => is_string($value) ? ($value . '|' . (string) $label) : (string) $label)
            ->implode("\n");

        return [
            'location'            => $this->activeFieldSettings,
            'field'               => $field,
            'payload'             => $payload,
            'supportsOptions'     => in_array($handle, $optionHandles, true),
            'supportsPlaceholder' => in_array($handle, $placeholderHandles, true),
            'supportsRows'        => $handle === 'textarea',
            'optionsText'         => $optionsText,
        ];
    }

    /**
     * Validate that activeFieldSettings points to a valid field. Close panel if invalid.
     * 
     * @return void
     */
    private function validateActiveFieldSettings(): void {
        if (!$this->activeFieldSettings) {
            return;
        }

        $payload = $this->getFieldPayloadAt($this->activeFieldSettings);

        if (!is_array($payload) || ($payload['handle'] ?? null) === 'repeater') {
            $this->closeFieldSettings();
            return;
        }

        if (!$this->hydrator->makeFieldFromPayload($payload)) {
            $this->closeFieldSettings();
        }
    }

    /**
     * Select a field for settings. Repeater fields route to the repeater builder.
     *
     * @param array<string, int|null> $location
     * 
     * @return void
     */
    private function selectFieldSettings(array $location): void {
        $payload = $this->getFieldPayloadAt($location);

        if (!is_array($payload)) {
            $this->closeFieldSettings();
            $this->closeRepeaterBuilder();
            return;
        }

        if (($payload['handle'] ?? null) === 'repeater') {
            $this->closeFieldSettings();
            $this->selectRepeaterField($location);
            return;
        }

        $this->closeRepeaterBuilder();
        $this->activeFieldSettings = $location;
    }

    /**
     * If a settings panel is open, focus it on the newly added field.
     *
     * @param array<string, int|null> $location
     * 
     * @return void
     */
    private function focusNewlyAddedFieldSettings(array $location): void {
        if ($this->activeFieldSettings === null && $this->activeRepeater === null) {
            return;
        }

        $this->selectFieldSettings($location);
    }
}
