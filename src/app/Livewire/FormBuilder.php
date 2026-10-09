<?php

namespace MM\Meros\App\Livewire;

use Livewire\Component;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Facades\Components\Fields;

class FormBuilder extends Component {
    public array $formData = [];
    public array $rows = [];
    private Repeater $componentTemplate;

    public function mount(array $formData, Repeater $componentTemplate): void {
        $this->formData = $formData;
        $this->rows     = $formData['rows'] ?? [];
        $this->componentTemplate = $componentTemplate;
    }

    public function render() {
        return view('meros::forms.builder');
    }

    public function getFieldComponent(int $rowIndex, string $fieldName): ?string {
        $row = $this->rows[$rowIndex] ?? null;

        if ($row === null) {
            return null;
        }

        $rowFields = collect($row['fields'] ?? []);
        $field = $rowFields->where('name', $fieldName)->first();

        if (!is_array($field)) {
            return null;
        }

        $fieldIndex = $rowFields->search(fn ($rowField) => is_array($rowField) && ($rowField['name'] ?? null) === $fieldName);

        if (!is_int($fieldIndex)) {
            return null;
        }

        return $this->renderFieldComponent(
            $field,
            "form_components[{$rowIndex}]['fields'][{$fieldIndex}]"
        );
    }

    public function getGroupFieldComponent(int $rowIndex, int $groupRowIndex, int $fieldIndex, array $field): ?string {
        $groupPrefix = "form_components[{$rowIndex}]['childGroup']['rows'][{$groupRowIndex}]['fields'][{$fieldIndex}]";

        return $this->renderFieldComponent($field, $groupPrefix);
    }

    private function renderFieldComponent(array $field, string $name): ?string {
        $component = Fields::cloneField($this->componentTemplate, $name);

        if (!($component instanceof Repeater)) {
            return null;
        }

        $component->label('Field Component');
        $component->default([
            [
                'field_type'        => $field['type'] ?? $field['field_type'] ?? '',
                'field_name'        => $field['name'] ?? '',
                'field_label'       => $field['label'] ?? '',
                'field_description' => $field['description'] ?? ''
            ]
        ]);

        return $component->html();
    }

    public function getNewFieldComponent(string $name = "form_components[0]['fields'][0]"): ?string {
        return $this->renderFieldComponent([
            'type'        => 'text',
            'name'        => '__FIELD_NAME__',
            'label'       => 'New Field',
            'description' => ''
        ], $name);
    }
}