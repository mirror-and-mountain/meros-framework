<?php

namespace MM\Meros\App\Livewire;

use Livewire\Component;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Components\Fields;
use MM\Meros\Support\FormTemplateComponentLocks;
use MM\Meros\Support\FormConditionSources;
use MM\Meros\Facades\Support\FormDynamicValues;

class FormBuilder extends Component {
    /**
     * The form's structure data.
     *
     * @var array
     */
    public array $formData = [];

    /**
     * The rows array extracted from the form's structure data.
     *
     * @var array
     */
    public array $rows = [];

    /**
     * Whether the current form is a FormTemplate instance.
     *
     * @var boolean
     */
    public bool $isFormTemplate = false;

    /**
     * The settings provided by the form host's template.
     *
     * @var array
     */
    public array $templateSettings = [];
    public array $templateLockSettings = [];

    /**
     * The actions provided by the form host's template.
     *
     * @var array
     */
    public array $templateActions = [];

    /**
     * The fields provided by the form host's template.
     *
     * @var array
     */
    public array $templateFields = [];

    /**
     * The rows provided by the form host's template.
     *
     * @var array
     */
    public array $templateRows = [];
    public int $postId = 0;
    public string $repeaterDefaultsAction = '';
    public ?string $repeaterDefaultsNonce = null;

    /**
     * The component template used to render form components.
     *
     * @var Repeater
     */
    private Repeater $componentTemplate;

    /**
     * The repeater field template used to render repeater fields.
     *
     * @var Repeater
     */
    private Repeater $repeaterFieldTemplate;

    public function mount(
        array $formData,
        Repeater $componentTemplate,
        Repeater $repeaterFieldTemplate,
        bool  $isFormTemplate = false,
        array $templateSettings = [],
        array $templateActions = [],
        array $templateFields = [],
        array $templateRows = [],
        int $postId = 0,
        string $repeaterDefaultsAction = '',
        ?string $repeaterDefaultsNonce = null,
        ?array $templateLockSettings = null,
    ): void {
        $this->formData = $formData;
        $this->rows     = $formData['rows'] ?? [];

        $this->componentTemplate     = $componentTemplate;
        $this->repeaterFieldTemplate = $repeaterFieldTemplate;

        $this->isFormTemplate       = $isFormTemplate;
        $this->templateSettings     = $templateSettings;
        $this->templateLockSettings = $templateLockSettings ?? $templateSettings;
        $this->templateActions      = $templateActions;
        $this->templateFields       = $templateFields;
        $this->templateRows         = $templateRows;

        $this->postId = $postId;
        $this->repeaterDefaultsAction = $repeaterDefaultsAction;
        $this->repeaterDefaultsNonce  = $repeaterDefaultsNonce;
    }

    public function render() {
        return view('meros::forms.builder', [
            'conditionSources' => FormConditionSources::choices(),
            'dynamicValues' => FormDynamicValues::choices(),
        ]);
    }

    /**
     * Retrieves the lock flags for a specific field component based on the template and settings.
     *
     * @param array $field
     *
     * @return array
     */
    public function fieldLocks(array $field): array {
        $template = $this->templateFields[$field['name'] ?? ''] ?? null;
        return FormTemplateComponentLocks::flags($field, is_array($template) ? $template : null, $this->templateSettings, $this->isFormTemplate);
    }

    /**
     * Retrieves the lock flags for a specific group component based on the template and settings.
     *
     * @param array $group
     *
     * @return array
     */
    public function groupLocks(array $group): array {
        $template = FormTemplateComponentLocks::groups($this->templateRows)[$group['name'] ?? ''] ?? null;
        return FormTemplateComponentLocks::flags($group, is_array($template) ? $template : null, $this->templateSettings, $this->isFormTemplate);
    }

    /**
     * Retrieves the template configuration for a specific group component.
     *
     * @param array $group
     *
     * @return array|null
     */
    public function templateGroup(array $group): ?array {
        return FormTemplateComponentLocks::groups($this->templateRows)[$group['name'] ?? ''] ?? null;
    }

    /**
     * Determines if a group contains any fields that are locked for a specific flag.
     *
     * @param array  $group
     * @param string $flag
     *
     * @return boolean
     */
    public function groupContainsLockedField(array $group, string $flag): bool {
        foreach ($group['rows'] ?? [] as $row) {
            foreach ($row['fields'] ?? [] as $field) {
                if ($this->fieldLocks($field)[$flag]) {
                    return true;
                }
            }
            if (!empty($row['childGroup']) &&
                ($this->groupLocks($row['childGroup'])[$flag] || $this->groupContainsLockedField($row['childGroup'], $flag))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieves the rendered HTML for a specific field component within a row.
     *
     * @param integer $rowIndex
     * @param string  $fieldName
     *
     * @return string|null
     */
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

    /**
     * Retrieves the rendered HTML for a specific field component within a group row.
     *
     * @param integer $rowIndex
     * @param integer $groupRowIndex
     * @param integer $fieldIndex
     * @param array   $field
     *
     * @return string|null
     */
    public function getGroupFieldComponent(int $rowIndex, int $groupRowIndex, int $fieldIndex, array $field): ?string {
        $groupPrefix = "form_components[{$rowIndex}]['childGroup']['rows'][{$groupRowIndex}]['fields'][{$fieldIndex}]";

        return $this->renderFieldComponent($field, $groupPrefix);
    }

    /**
     * Renders the HTML for a given field component based on its configuration and name.
     *
     * @param array  $field
     * @param string $name
     *
     * @return string|null
     */
    public function renderFieldComponent(array $field, string $name): ?string {
        if (($field['type'] ?? $field['field_type'] ?? '') === 'repeater') {
            return $this->renderRepeaterComponent($field, $name);
        }

        $component = Fields::cloneField($this->componentTemplate, $name);

        if (!($component instanceof Repeater)) {
            return null;
        }

        if (array_key_exists('options', $field)) {
            $field['options'] = Select::getConfigurableOptions($field['options']);
        }

        $component->label('Field Component');

        $fieldName          = $field['name'] ?? '';
        $isTemplateField    = $this->isTemplateField($fieldName);
        $locks = $this->fieldLocks($field);
        $isLockedByTemplate = $locks['__properties_locked_by_template'];

        $fieldName = $isTemplateField && $isLockedByTemplate
            ? ($this->templateFields[$fieldName]['name'] ?? '')
            : $fieldName;

        $fieldLabel = $isTemplateField && $isLockedByTemplate
            ? ($this->templateFields[$fieldName]['label'] ?? '')
            : ($field['label'] ?? '');

        $fieldDescription = $isTemplateField && $isLockedByTemplate
            ? ($this->templateFields[$fieldName]['description'] ?? '')
            : ($field['description'] ?? '');

        $formData = $isTemplateField && $isLockedByTemplate
            ? $this->templateFields[$fieldName] ?? []
            : $field;

        $component->default([
            [
                'field_type'              => $field['type'] ?? $field['field_type'] ?? '',
                'field_name'              => $fieldName,
                'field_label'             => $fieldLabel,
                'field_description'       => $fieldDescription,
                '__is_template_field'     => $isTemplateField,
                ...$locks,
                '__is_form_template'      => $this->isFormTemplate,
                '__form_data'             => json_encode($formData)
            ]
        ]);

        return $component->html();
    }

    /**
     * Renders a rich text description control for the form builder.
     *
     * @param string  $name
     * @param string  $id
     * @param string  $label
     * @param string  $value
     * @param boolean $readonly
     *
     * @return string
     */
    public function renderDescriptionControl(string $name, string $id, string $label, string $value = '', bool $readonly = false): string {
        $control = Fields::checkout($this->componentTemplate->getProvider())->makeFrom('richtext', [
            'id'       => $id,
            'name'     => $name,
            'label'    => $label,
            'default'  => $value,
            'readonly' => $readonly,
        ]);

        $control->wrapper('settings', $control->getWrapper('site'));
        return $control->html();
    }

    /**
     * Renders the repeater field component for the form builder, applying template lock settings if necessary.
     *
     * @param array  $field
     * @param string $name
     *
     * @return string
     */
    private function renderRepeaterComponent(array $field, string $name): string {
        if ($this->repeaterFieldTemplate === null) {
            throw new \LogicException('The repeater field editor template is unavailable.');
        }

        $fieldName       = $field['name'] ?? '';
        $isTemplateField = $this->isTemplateField($fieldName);
        $locks = $this->fieldLocks($field);
        $locked = $locks['__properties_locked_by_template'];

        if ($isTemplateField && $locked) {
            $field = $this->templateFields[$fieldName];
        }

        $component = Fields::cloneField($this->repeaterFieldTemplate, "{$name}[repeater_fields]");
        if (!($component instanceof Repeater)) {
            throw new \RuntimeException('Failed to create the repeater field editor.');
        }

        $columns = [];
        foreach ($field['fields'] ?? [] as $column) {
            if (array_key_exists('options', $column)) {
                $column['options'] = Select::getConfigurableOptions($column['options']);
            }
            $type = $column['type'] ?? '';
            if (Fields::getRegisteredFeatureClass($type) === null) {
                $type = str_replace('_', '-', $type);
            }
            $columns[] = [
                'field_type'        => $type,
                'field_name'        => $column['name'] ?? '',
                'field_label'       => $column['label'] ?? '',
                'field_description' => $column['description'] ?? '',
                '__form_data'       => json_encode($column, JSON_THROW_ON_ERROR),
            ];
        }
        $component->default($columns);

        $provider = $component->getProvider();
        $editorId = $component->getId();

        $nameControl = Fields::checkout($provider)->makeFrom('text', [
            'id'      => $editorId . '-name',
            'name'    => "{$name}[field_name]",
            'label'   => 'Repeater name',
            'default' => $fieldName,
        ]);

        $labelControl = Fields::checkout($provider)->makeFrom('text', [
            'id'      => $editorId . '-label',
            'name'    => "{$name}[field_label]",
            'label'   => 'Repeater label',
            'default' => $field['label'] ?? '',
        ]);

        $lockControls = [];
        if ($this->isFormTemplate) {
            foreach ([
                '__properties_locked_by_template' => 'Lock Field Properties',
                '__move_locked_by_template' => 'Prevent Moving',
                '__remove_locked_by_template' => 'Prevent Removal',
            ] as $flag => $label) {
                $lockControls[$flag] = Fields::checkout($provider)->makeFrom('checkbox', [
                    'id' => $editorId . '-' . $flag,
                    'name' => "{$name}[{$flag}]",
                    'label' => $label,
                    'default' => $locks[$flag],
                    'toggle' => true,
                    'attributes' => ['value' => '1'],
                ])->toArray();
            }
        }

        return view('meros::forms.builder-repeater', [
            'name'            => $name,
            'field'           => $field,
            'fieldName'       => $fieldName,
            'isTemplateField' => $isTemplateField,
            'isFormTemplate'  => $this->isFormTemplate,
            'locked'          => $locked,
            'editorId'        => $editorId,
            'nameControl'     => $nameControl->toArray(),
            'labelControl'    => $labelControl->toArray(),
            'locks'           => $locks,
            'lockControls'    => $lockControls,
            'descriptionHtml' => $this->renderDescriptionControl("{$name}[field_description]", $editorId . '-description', 'Repeater description', $field['description'] ?? '', $locked && !$this->isFormTemplate),
            'columnsHtml'     => $component->html(),
        ])->render();
    }

    /**
     * Checks if a given field is a template field.
     *
     * @param string $fieldName
     *
     * @return bool
     */
    private function isTemplateField(string $fieldName): bool {
        return collect($this->templateFields)->firstWhere('name', $fieldName) !== null;
    }

    /**
     * Gets a new field component with default configuration.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getNewFieldComponent(string $name = "form_components[0]['fields'][0]"): ?string {
        return $this->renderFieldComponent([
            'type'        => 'text',
            'name'        => '__FIELD_NAME__',
            'label'       => 'New Field',
            'description' => ''
        ], $name);
    }

    public function getNewRepeaterComponent(string $name = "form_components[0]['fields'][0]"): string {
        return $this->renderRepeaterComponent([
            'type' => 'repeater',
            'name' => '__FIELD_NAME__',
            'label' => 'New Repeater',
            'description' => '',
            'fields' => [],
        ], $name);
    }
}