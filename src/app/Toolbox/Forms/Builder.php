<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\FormActions;

use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\PostMeta as FormMeta;

use MM\Meros\App\Toolbox\Forms\Concerns\ManagesFormSchema;

use MM\Meros\App\Toolbox\Forms\Helpers\Hydrator;
use MM\Meros\App\Toolbox\Forms\Helpers\Serializer;
use MM\Meros\App\Toolbox\Forms\Helpers\Utilities;

class Builder extends Component {
    /**
     * Nav Items to be rendered in the builder's navigation bar.
     *
     * @var array
     */
    public array $navItems = [];

    /**
     * The url to return to when clicking the wordpress link in the header.
     *
     * @var string
     */
    public string $returnUrl = '';

    /**
     * The repeater field currently being edited (if any).
     *
     * @var string|null
     */
    public ?string $editingRepeaterID = null;

    /**
     * The field currently being edited for conditions (if any).
     *
     * @var string|null
     */
    public ?string $editingFieldID = null;

    /**
     * The repeater field instance currently being edited (if any).
     *
     * @var Field|null
     */
    private ?Field $editingRepeaterField = null;

    /**
     * The field instance currently being edited for conditions (if any).
     *
     * @var Field|null
     */
    private ?Field $editingField = null;

    /**
     * A collection of hydrated elements used in the schema.
     *
     * @var Collection|null
     */
    private ?Collection $elements = null;

    use ManagesFormSchema;

    public function mount(string|int|null $formID = null) {
        $this->initialiseFields();
        $this->initialiseFieldGroups();
        $this->initialiseFormActions();

        if ($formID) {
            $this->formID = $formID;
            $this->form   = Form::find($formID);

            $this->navItems = [
                0 => 'Settings',
                1 => 'Canvas',
                'Preview' => get_preview_post_link($formID)
            ];

            $this->returnUrl = admin_url('edit.php?post_type=meros-form');
        } else {
            $this->makeNewForm();
        }

        $rawSchema = [
            'rows'    => [],
            'actions' => []
        ];

        if ($this->form) {
            $rawSchema = $this->loadFormSchema($this->form->schema);
        } 

        $this->schema = [
            'rows'     => Utilities::normaliseRowPayloads($rawSchema['rows'] ?? []),
            'actions'  => $this->normaliseActionPayloads($rawSchema['actions'] ?? [])
        ];

        $this->rowPayloads    = $this->schema['rows'] ?? [];
        $this->actionPayloads = $this->schema['actions'] ?? [];
    }

    public function render() {
        $hydrator = Hydrator::make($this->fieldTypes, $this);
        $hydratedRows = $hydrator->hydrateRowPayloads($this->rowPayloads);

        return view('meros::toolbox.forms.builder.index', [
            'formID'          => $this->formID,
            'formTitle'       => $this->formTitle,
            'formDescription' => $this->formDescription,
            'canvasRows'      => $hydratedRows,
            'editingRepeater' => $this->editingRepeaterField,
            'editingField'    => $this->editingField,
        ])
            ->layout('meros::toolbox.layout', [
                'navItems'  => $this->navItems,
                'returnUrl' => $this->returnUrl
            ]);
    }

    // =========================================================================
    // Schema Update Methods
    // =========================================================================

    /**
     * Updates a specific setting of the form being edited and dispatches a schema update event.
     *
     * @param string $settingKey
     * @param mixed  $settingValue
     *
     * @return void
     */
    public function updateSettings(string $settingKey, mixed $settingValue): void {
        if (property_exists($this, $settingKey)) {
            $this->{$settingKey} = $settingValue;
        }

        $this->dispatchSchemaUpdate();
    }

    /**
     * Updates the form actions and dispatches a schema update event.
     *
     * @param array $actions
     *
     * @return void
     */
    public function updateActions(array $actions): void {
        $normalisedActions = $this->normaliseActionPayloads($actions);

        $this->schema['actions'] = $normalisedActions;
        $this->actionPayloads = $normalisedActions;

        $this->dispatchSchemaUpdate();
    }

    /**
     * Updates the form schema rows with new row payloads.
     *
     * @param array $updatedSchemaRows
     *
     * @return void
     */
    public function updateRows(array $updatedSchemaRows, bool $closeEditingPanel = false): void {
        $updatedSchemaRows = $this->normaliseFieldGroupRows($updatedSchemaRows);

        $this->schema['rows'] = $updatedSchemaRows;
        $this->rowPayloads    = $updatedSchemaRows;

        if ($this->editingFieldID !== null && !$closeEditingPanel) {
            $this->editingField = $this->getEditingField($this->editingFieldID);
        }

        else if ($this->editingRepeaterID !== null && !$closeEditingPanel) {
            $this->editingRepeaterField = $this->getEditingRepeaterField($this->editingRepeaterID);
        }

        else if ($closeEditingPanel) {
            $this->editingFieldID = null;
            $this->editingField = null;
            $this->editingRepeaterID = null;
            $this->editingRepeaterField = null;
        }

        $this->dispatchSchemaUpdate();
    }

    /**
     * Sets the ID of the field currently being edited for conditions.
     *
     * @param string|null $fieldID
     *
     * @return void
     */
    public function setEditingFieldID(string|null $fieldID): void {
        $this->editingFieldID = $fieldID;

        if ($fieldID === null) {
            $this->editingField = null;
        } else {
            $this->editingField = $this->getEditingField($fieldID);
        }

        $this->dispatchSchemaUpdate();
    }

    // =========================================================================
    // Conditions Management Methods
    // =========================================================================

    /**
     * Builds and returns repeater field instances for managing the conditions of the field currently being edited.
     *
     * @return array
     */
    public function getFieldConditionsRepeaters(): array {
        if ($this->editingField === null) {
            return [];
        }

        $conditions = $this->editingField->getConditions() ?? [];
        $currentFields = [];

        $this->walkFormFields($this->rowPayloads, function($field) use (&$currentFields) {
            if (($field['properties']['id'] ?? null) !== null) {
                $currentFields[] = [
                    'id'    => $field['properties']['id'],
                    'label' => $field['properties']['label'] ?? 'Untitled Field',
                    'type'  => $field['handle'] ?? 'unknown'
                ];
            }
        }, false);

        $currentFields = collect($currentFields)->filter(function ($field) {
            return $field['id'] !== $this->editingFieldID;
        })->values();

        $currentFieldLabels = $currentFields->pluck('label', 'id')->toArray();
        $currentFieldHandlesById = $currentFields->pluck('type', 'id')->toArray();

        $repeaters = [];
        foreach ($conditions as $type => $configuration) {
            $rules = $configuration['rules'] ?? [];
            $operatorOptions = $this->getFieldConditionOperatorOptionsForRules($rules, $currentFieldHandlesById);
            $rulesForRepeaterDefault = $this->normaliseFieldConditionRulesForRepeaterDefault($rules);

            $repeater = Fields::checkout(Framework::get())->makeFrom('repeater', [
                'id'             => 'field-conditions-' . $type,
                'name'           => 'field_conditions_' . $type,
                'allowConfigure' => false,
                'onAddRow'       => '$store.formBuilder.handleFieldConditionsRepeaterAddRow',
                'onRemoveRow'    => '$store.formBuilder.syncFieldConditionsRepeaterSelectionState',
                'onMoveRow'      => '$store.formBuilder.syncFieldConditionsRepeaterSelectionState',
                'addRowText'     => 'Add Condition',
                'placeholder'    => 'No conditions added yet.'
            ]);

            $repeater->class('meros-field-conditions-repeater');

            $repeater->subField('select')
                ->name('field_id')
                ->label('Field Name')
                ->options(array_merge(['' => 'Select a field'], $currentFieldLabels))
                ->onChange('$store.formBuilder.setFieldConditionsRow');

            $repeater->subField('select')
                ->name('operator')
                ->label('Operator')
                ->options($operatorOptions)
                ->onChange('$store.formBuilder.setFieldConditionOperatorRow');

            $repeater->subField('text')
                ->name('value')
                ->label('Value')
                ->disabled();

            $repeater->default($rulesForRepeaterDefault);

            $repeaters[$type] = $repeater;
        }

        return $repeaters;
    }

    /**
     * Normalises condition rules so repeater default hydration remains safe for text fallback fields.
     *
     * @param array $rules
     *
     * @return array
     */
    private function normaliseFieldConditionRulesForRepeaterDefault(array $rules): array {
        return array_map(function($rule) {
            if (!is_array($rule)) {
                return $rule;
            }

            if (!array_key_exists('value', $rule)) {
                return $rule;
            }

            $value = $rule['value'];

            if (is_array($value) || is_object($value)) {
                $rule['value'] = json_encode($value);
            }

            return $rule;
        }, $rules);
    }

    /**
     * Builds field-condition operator options for a ruleset by resolving each rule's field handle.
     *
     * @param array $rules
     * @param array $fieldHandlesById
     *
     * @return array
     */
    private function getFieldConditionOperatorOptionsForRules(array $rules, array $fieldHandlesById): array {
        if (empty($rules)) {
            return [];
        }

        $allowedOperators = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $fieldId = (string) ($rule['field_id'] ?? '');
            $fieldType = $fieldHandlesById[$fieldId] ?? null;

            foreach ($this->getFieldConditionOperatorsForFieldType($fieldType) as $operator) {
                if (!in_array($operator, $allowedOperators, true)) {
                    $allowedOperators[] = $operator;
                }
            }

            $savedOperator = (string) ($rule['operator'] ?? '');

            if ($savedOperator !== '' && !in_array($savedOperator, $allowedOperators, true)) {
                $allowedOperators[] = $savedOperator;
            }
        }

        if (empty($allowedOperators)) {
            return [];
        }

        $options = ['' => 'Select operator'];

        foreach ($allowedOperators as $operator) {
            $options[$operator] = $this->formatFieldConditionOperatorLabel($operator);
        }

        return $options;
    }

    /**
     * Returns available field-condition operators for a field type.
     * Mirrors the operator map in formBuilderStore.
     *
     * @param string|null $fieldType
     *
     * @return array
     */
    private function getFieldConditionOperatorsForFieldType(?string $fieldType): array {
        $operatorMap = $this->getFieldConditionOperatorMap();

        if ($fieldType === null || $fieldType === '') {
            return [];
        }

        return $operatorMap[$fieldType] ?? ['equals', 'not_equals', 'is_empty', 'is_not_empty'];
    }

    /**
     * Returns the canonical field-condition operator map.
     *
     * @return array
     */
    public function getFieldConditionOperatorMap(): array {
        return [
            'text'            => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'textarea'        => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'rich_text'       => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'email'           => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'url'             => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'tel'             => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'hidden'          => ['equals', 'not_equals', 'contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'number'          => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal_to', 'less_than_or_equal_to', 'is_empty', 'is_not_empty'],
            'range'           => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal_to', 'less_than_or_equal_to', 'is_empty', 'is_not_empty'],
            'select'          => ['equals', 'not_equals'],
            'advanced_select' => ['equals', 'not_equals'],
            'radio'           => ['equals', 'not_equals'],
            'multi_select'    => ['contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'checkboxes'      => ['contains', 'does_not_contain', 'is_empty', 'is_not_empty'],
            'date'            => ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'is_empty', 'is_not_empty'],
            'time'            => ['equals', 'not_equals', 'before', 'after', 'on_or_before', 'on_or_after', 'is_empty', 'is_not_empty'],
            'checkbox'        => ['is_checked', 'is_unchecked'],
            'repeater'        => ['is_empty', 'is_not_empty']
        ];
    }

    /**
     * Formats an operator key for select-option labels.
     *
     * @param string $operator
     *
     * @return string
     */
    private function formatFieldConditionOperatorLabel(string $operator): string {
        return Str::title(str_replace('_', ' ', $operator));
    }

    /**
     * Retrieves a field instance currently being edited.
     *
     * @param string        $fieldID
     * @param callable|null $predicate Additional field filter callback.
     *
     * @return Field|null
     */
    private function getEditingField(string $fieldID, ?callable $predicate = null): ?Field {
        $match = $this->findFirstFormField($this->rowPayloads, function($field) use ($fieldID, $predicate) {
            $matchesID = ($field['properties']['id'] ?? null) === $fieldID;

            if (!$matchesID) {
                return false;
            }

            if ($predicate !== null) {
                return $predicate($field) === true;
            }

            return true;
        });

        if (!$match) {
            return null;
        }

        return Hydrator::make($this->fieldTypes, $this)->hydrateFieldPayload($match['field']);
    }

    // =========================================================================
    // Group Management Methods
    // =========================================================================

    /**
     * Normalises field group rows in the provided schema rows, merging them with registered field group configurations where applicable.
     *
     * @param array $rows
     *
     * @return array
     */
    private function normaliseFieldGroupRows(array $rows): array {
        $this->walkFormGroups($rows, function($group, $location) use (&$rows) {
            $handle = $group['handle'] ?? null;

            if ($handle === null) {
                return;
            }

            if (in_array($handle, array_keys($this->fieldGroups))) {
                $normalisedGroup = $this->normaliseFieldGroupInstance($handle);
                if ($normalisedGroup === null) {
                    return;
                }

                $rows[$location['rowIndex']]['group'] = array_merge(
                    $group,
                    $normalisedGroup
                );
            }
        });

        return $rows;
    }

    /**
     * Instantiates a FieldGroup instance by handle and normalises it for use in the builder.
     * Used for handling preset field groups available in the builder.
     *
     * @param string $handle
     *
     * @return array|null
     */
    private function normaliseFieldGroupInstance(string $handle): ?array {
        if (!isset($this->fieldGroups[$handle])) {
            return null;
        }

        $instance = FieldGroups::checkout(Framework::get())->get($handle);

        if ($instance === null) {
            return null;
        }

        $groupRows       = $instance->getRows();
        $parsedGroupRows = [];

        foreach ($groupRows as $index => $groupRow) {
            $parsedGroupRows[$index]['fields'] = $groupRow->getFields(true);

            foreach ($parsedGroupRows[$index]['fields'] as $fieldIndex => $field) {
                if ($field instanceof Field) {
                    $parsedGroupRows[$index]['fields'][$fieldIndex] = $field->toJson();
                }
            }
        }

        $id = $instance->getID();

        return [
            'id'          => empty($id) ? 'field_' . Str::substr(Str::uuid(), 0, 8) : $id,
            'handle'      => '', // Clear out the handle so we don't renormalise the instance later.
            'title'       => $instance->getTitle(),
            'description' => $instance->getDescription(),
            'rows'        => $parsedGroupRows
        ];
    }

    // =========================================================================
    // Schema retrieval and element management methods
    // =========================================================================

    /**
     * Retrieves the form's settings as an array.
     *
     * @return array
     */
    public function getSettings(): array {
        return [
            'title'       => $this->formTitle,
            'description' => $this->formDescription,
            'slug'        => $this->formSlug,
            'status'      => $this->formStatus
        ];
    }

    /**
     * Retrieves the form's actions as an array.
     *
     * @return array
     */
    public function getActions(): array {
        return $this->normaliseActionPayloads($this->schema['actions'] ?? []);
    }

    /**
     * Retrieves the form's available action payloads as an array.
     *
     * @return array
     */
    public function getActionPayloads(): array {
        return $this->normaliseActionPayloads($this->actionPayloads ?? []);
    }

    /**
     * Retrieves the form schema rows for rendering in the canvas.
     *
     * @return array
     */
    #[Renderless]
    public function getRows(): array {
        return $this->schema['rows'] ?? [];
    }

    /**
     * Adds a hydrated element to the collection of elements used in the schema.
     *
     * @param Field|FieldGroup $element
     *
     * @return void
     */
    public function addElement(Field|FieldGroup $element): void {
        $this->elements = $this->elements ?? collect([]);
        $this->elements->push($element);
    }

    // =========================================================================
    // Action Management
    // =========================================================================

    /**
     * Retrieves the repeater field instance used for managing form actions in the builder.
     *
     * @return Field
     */
    public function getActionsRepeaterField(): Field {
        $this->initialiseFormActions();

        $existingActions = [];

        foreach ($this->normaliseActionPayloads($this->actionPayloads) as $handle => $_payload) {
            $actionType = Str::before($handle, '__');
            $actionId = Str::after($handle, '__');

            if ($actionType === '') {
                continue;
            }

            if ($actionId === '' || $actionId === $actionType) {
                $actionId = 'action_' . Str::substr(Str::uuid(), 0, 8);
            }

            $existingActions[] = [
                'action_label' => empty($_payload['label']) ?? '' ? Str::title(Str::replace(['-', '_'], ' ', $actionType)) : $_payload['label'],
                'action_type'  => $actionType,
                'action_id'    => $actionId,
            ];
        }

        $field = Fields::checkout(Framework::get())->makeFrom(
            'repeater', [
                'id'                    => 'meros-form-actions-repeater',
                'label'                 => 'Form Actions',
                'name'                  => 'form_actions',
                'default'               => $existingActions,
                'addRowText'            => 'Add Action',
                'onAddRow'              => '$store.formBuilder.onActionRowAdded',
                'onRemoveRow'           => '$store.formBuilder.onActionRowRemoved',
                'onMoveRow'             => '$store.formBuilder.onActionRowMoved',
                'onConfigureRow'        => '$store.formBuilder.onActionRowConfigure'
            ]
        );

        $field->configureRequiredFields(['action_type']);

        $field->subField('select', function($select) {
            $select->label('Action Type');
            $select->name('action_type');
            $select->onChange('$store.formBuilder.saveActions');
            
            $options = [];

            foreach($this->availableActions as $handle => $payload) {
                $options[$handle] = $payload['label'];
            }

            $select->options($options);
        });

        $field->subField('text')
            ->label('Label')
            ->name('action_label')
            ->onChange('$store.formBuilder.saveActions');

        $field->subField('text')
            ->label('Action ID')
            ->name('action_id')
            ->disabled();

        return $field;
    }

    /**
     * Retrieves the configuration dialog for a specific form action type, rendered as an HTML string.
     *
     * @param string $uniqueActionHandle
     * @param array  $formFields
     *
     * @return string
     */
    public function getActionConfigurationDialog(string $uniqueActionHandle, array $formFields = [], array $config = []): string {
        $this->initialiseFormActions();

        //// Here. We'll get form fields in the same way do as in conditions rather than receiving from the front end.

        $actionHandle = Str::before($uniqueActionHandle, '__');

        if (!in_array($actionHandle, array_keys($this->availableActions))) {
            return '';
        }

        $actionInstance = FormActions::checkout(Framework::get())->makeFrom($actionHandle);

        if (!$actionInstance) {
            return '';
        }

        $config = $config !== []
            ? $config
            : ($this->normaliseActionPayloads($this->actionPayloads)[$uniqueActionHandle]['config'] ?? []);

        return $actionInstance->renderConfigurationDialog($formFields, $config);

    }

    /**
     * Normalises action payloads via Utilities helper.
     *
     * @param mixed $actions
     *
     * @return array
     */
    private function normaliseActionPayloads(mixed $actions): array {
        $normaliser = [Utilities::class, 'normaliseActionPayloads'];

        if (is_callable($normaliser)) {
            return call_user_func($normaliser, $actions);
        }

        return [];
    }

    // =========================================================================
    // Repeater Fields Management
    // =========================================================================

    /**
     * Sets the ID of the repeater currently being edited.
     *
     * @param string|null $fieldID The repeater's field ID or null to clear the editing state.
     *
     * @return void
     */
    public function setEditingRepeaterID(string|null $fieldID = null): void {
        $this->editingRepeaterID = $fieldID;

        if ($fieldID === null) {
            $this->editingRepeaterField = null;
        } else {
            $this->getEditingRepeaterField($fieldID);
        }

        $this->dispatchSchemaUpdate();
    }

    /**
     * Add a new field to the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldType
     * @param int    $position
     *
     * @return void
     */
    public function addRepeaterField(string $repeaterID, string $fieldType = 'text', int $position = 0): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $id = Str::uuid()->toString();

        $instance = Hydrator::make($this->fieldTypes, $this)->hydrateFieldPayload(
            [
                'handle' => $fieldType,
                'properties' => [
                    'id'       => 'field_' . Str::substr($id, 0, 8),
                    'label'    => ucfirst($fieldType),
                    'name'     => Str::slug($fieldType) . '_' . Str::substr($id, 0, 8),
                    'helpText' => '',
                    'value'    => '',
                    'required' => false,
                    'disabled' => false,
                    'width'    => 'full'
                ]
            ]
        );

        $repeater->field($instance, [
            'position' => $position
        ]);

        $this->saveRepeater($repeater);
    }

    /**
     * Updates the configuration of a field within the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldID
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function updateRepeaterField(string $repeaterID, string $fieldID, string $property, mixed $value): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $field = $repeater->getFields()->map(function($field) use ($fieldID) {
            return $field->getId() === $fieldID ? $field : null;
        })->filter()->first();

        if (!$field) {
            return;
        }

        if (method_exists($field, $property)) {
            $field->{$property}($value);
        }

        $this->saveRepeater($repeater);
    }

    /**
     * Removes a field from the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldID
     *
     * @return void
     */
    public function removeRepeaterField(string $repeaterID, string $fieldID): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $field = $repeater->getFields()->map(function($field) use ($fieldID) {
            return $field->getId() === $fieldID ? $field : null;
        })->filter()->first();

        if (!$field) {
            return;
        }

        $repeater->detach($field);
        $this->saveRepeater($repeater);
    }

    /**
     * Moves a field within the repeater currently being edited to a new position.
     *
     * @param string  $repeaterID
     * @param string  $fieldID
     * @param integer $newPosition
     *
     * @return void
     */
    public function moveRepeaterField(string $repeaterID, string $fieldID, int $newPosition): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        if ($newPosition < 0) {
            $newPosition = 0;
        }

        $fields = $repeater->getFields();

        if ($newPosition >= $fields->count()) {
            $newPosition = $fields->count() - 1;
        }

        $fieldIndex = $fields->search(function($field) use ($fieldID) {
            return $field->getId() === $fieldID;
        });

        if ($fieldIndex === false) {
            return;
        }

        $field = $fields[$fieldIndex];
        $fields->splice($fieldIndex, 1);
        $fields->splice($newPosition, 0, [$field]);

        $repeater->refreshFields($fields->all());
        $this->saveRepeater($repeater);
    }

    /**
     * Updates the value of the repeater currently being edited.
     *
     * @param array $value
     *
     * @return void
     */
    public function updateRepeaterDefaultValue(string $repeaterID, array $value): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $repeater->default($value);
        $this->saveRepeater($repeater);
    }

    public function closeEditingRepeater(): void {
        $this->editingRepeaterID = null;
        $this->editingRepeaterField = null;
        $this->dispatchSchemaUpdate();
    }

    /**
     * Retrieves the repeater field instance currently being edited.
     *
     * @param string $repeaterID The ID of the repeater field to retrieve.
     *
     * @return Field|null
     */
    private function getEditingRepeaterField(string $repeaterID): ?Field {
        $repeater = $this->getEditingField($repeaterID, function($field) {
            return ($field['handle'] ?? null) === 'repeater';
        });

        $this->editingRepeaterField = $repeater;
        return $repeater;
    }

    /**
     * Saves the updated repeater field back into the form schema.
     *
     * @param Field $updatedRepeater The updated repeater field instance to save.
     *
     * @return void
     */
    private function saveRepeater(Field $updatedRepeater): void {
        $payload = $updatedRepeater->toJson();

        $match = $this->findFirstFormField($this->rowPayloads, function($field) use ($payload) {
            return ($field['handle'] ?? null) === 'repeater'
                && ($field['properties']['id'] ?? null) === ($payload['properties']['id'] ?? null);
        });

        if (!$match) {
            return;
        }

        $existing = $match['location'];

        if (isset($existing['groupRowIndex'])) {
            $this->rowPayloads[$existing['rowIndex']]['group']['rows'][$existing['groupRowIndex']]['fields'][$existing['fieldIndex']] = $payload;
        } else {
            $this->rowPayloads[$existing['rowIndex']]['fields'][$existing['fieldIndex']] = $payload;
        }

        $this->dispatchSchemaUpdate();
        session()->flash('updateStatus', 'Repeater updated!');
    }

    // =========================================================================
    // TomSelect Fields Management
    // =========================================================================

    /**
     * Retrieves advanced select payloads from the provided schema rows.
     *
     * @return array
     */
    private function getAdvancedSelectPayloads(): array {
        return $this->getFieldTypePayloads('advanced_select', $this->rowPayloads);
    }

    // =========================================================================
    // Rich Text Fields Management
    // =========================================================================

    /**
     * Retrieves rich text payloads from the form schema's row payloads.
     *
     * @return array
     */
    public function getRichTextPayloads(): array {
        return $this->getFieldTypePayloads('rich_text', $this->rowPayloads);
    }

    /**
     * Retrieves payloads for a specific field type from the provided schema rows.
     *
     * @param string $fieldType
     * @param array  $rows
     *
     * @return array
     */
    private function getFieldTypePayloads(string $fieldType, array $rows): array {
        $payloads = [];

        if ($fieldType === 'rich_text') {
            $this->walkFormGroups($rows, function($group, $location) use (&$payloads) {
                if (!empty($group['description'])) {
                    $payloads[] = [
                        'rt_id'   => $location['rowIndex'],
                        'content' => $group['description'],
                    ];
                }
            });
        }

        $this->walkFormFields($rows, function($field, $location) use (&$payloads, $fieldType) {
            $payload = $this->buildFieldTypePayload($field, $location, $fieldType);

            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }, true);

        return $payloads;
    }

    /**
     * Builds a payload for a field based on the requested field type.
     *
     * @param array  $field
     * @param array  $location
     * @param string $fieldType
     *
     * @return array|null
     */
    private function buildFieldTypePayload(array $field, array $location, string $fieldType): ?array {
        $properties = $field['properties'] ?? [];

        if (
            $fieldType === 'advanced_select'
            && in_array($field['handle'] ?? null, ['advanced_select', 'multi_select'], true)
            && (($properties['advanced'] ?? false) === true)
        ) {
            return [
                'id'               => $properties['id'] ?? '',
                'label'            => $properties['label'] ?? '',
                'name'             => $properties['name'] ?? '',
                'helpText'         => $properties['helpText'] ?? '',
                'helpTextPosition' => $properties['helpTextPosition'] ?? 'top',
                'required'         => $properties['required'] ?? false,
                'disabled'         => $properties['disabled'] ?? false,
                'advanced'         => $properties['advanced'] ?? false,
                'allowAdd'         => $properties['allowAdd'] ?? false,
                'options'          => $properties['options'] ?? []
            ];
        }

        if ($fieldType === 'rich_text' && (($field['handle'] ?? null) === 'rich_text')) {
            $fallbackID = isset($location['repeaterFieldIndex'])
                ? "{$location['rowIndex']}_{$location['fieldIndex']}_{$location['repeaterFieldIndex']}"
                : "{$location['rowIndex']}_{$location['fieldIndex']}";

            return [
                'id'               => $properties['id'] ?? $fallbackID,
                'name'             => $properties['name'] ?? '',
                'label'            => $properties['label'] ?? '',
                'helpText'         => $properties['helpText'] ?? '',
                'helpTextPosition' => $properties['helpTextPosition'] ?? '',
                'required'         => $properties['required'] ?? false,
                'disabled'         => $properties['disabled'] ?? false,
                'rt_id'            => $properties['id'] ?? $fallbackID,
                'content'          => $properties['value'] ?? $properties['default'] ?? '',
            ];
        }

        return null;
    }

    /**
     * Renders Quill delta content to HTML, handling basic formatting and links.
     *
     * @param string $deltaJson
     *
     * @return string
     */
    public function renderQuillContent(string $deltaJson): string {
        $ops = json_decode($deltaJson, true) ?? [];
        $html = '';

        foreach ($ops as $op) {
            $text = $op['insert'] ?? '';
            $attrs = $op['attributes'] ?? [];

            // Escape HTML
            $text = e($text);

            if (!empty($attrs['bold'])) {
                $text = "<strong>{$text}</strong>";
            }
            if (!empty($attrs['underline'])) {
                $text = "<u>{$text}</u>";
            }
            if (!empty($attrs['link'])) {
                $url = htmlspecialchars($attrs['link'], ENT_QUOTES, 'UTF-8');
                $text = "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$text}</a>";
            }
            if (!empty($attrs['italic'])) {
                $text = "<em>{$text}</em>";
            }

            // Handle newlines (Quill uses "\n" as a separate insert)
            if ($text === "\n") {
                // If you are wrapping in <p>, you might skip this or use <br>
                // For a single root element, we often skip standalone newlines
                continue; 
            }

            $html .= $text;
        }

        return nl2br($html); // Convert newlines to <br> for HTML output
    }

    // =========================================================================
    // Saving and Default Schema
    // =========================================================================

    /**
     * Saves the form and its configuration to the database.
     *
     * @return void
     */
    public function saveForm(): void {
        if (!$this->form) {
            return;
        }

        $serializer = Serializer::make(Hydrator::make($this->fieldTypes, $this));

        $serializedSchema = [
            'actions'  => $this->actionPayloads ?? [],
            'rows'     => $serializer->serializeFormSchema($this->rowPayloads ?? [])
        ];
        
        $this->form->update([
            'post_title'   => $this->formTitle ?: 'Untitled Form',
            'post_name'    => Str::slug($this->formSlug ?: $this->formTitle ?: 'untitled-form'),
            'post_content' => wp_kses_post($this->formDescription ?: ''),
            'post_status'  => $this->formStatus ?: 'draft'
        ]);

        $this->form->meta()->updateOrCreate(
            ['meta_key'   => '_meros_form_meta'],
            ['meta_value' => json_encode([
                'schema'  => $serializedSchema
            ])]
        );

        $this->dispatchSchemaUpdate();
        session()->flash('updateStatus', 'Form successfully saved!');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Dispatches a schema update event with the current row payloads and advanced select field configurations.
     *
     * @return void
     */
    private function dispatchSchemaUpdate(): void {
        $advancedSelectPayloads = $this->getAdvancedSelectPayloads();
        $richTextPayloads = $this->getRichTextPayloads();

        // dd($advancedSelectPayloads, $richTextPayloads, $this->rowPayloads);

        $ignoredFields = array_merge(
            $advancedSelectPayloads,
            $richTextPayloads
        );

        $this->dispatch('schema-updated', [
            'rows'             => $this->rowPayloads,
            'richTextPayloads' => $richTextPayloads,
            'ignoredFields'    => $ignoredFields
        ]);
    }

    /**
     * Creates a new form post and redirects to its builder page.
     *
     * @return void
     */
    private function makeNewForm(): void {
        $newFormId = wp_insert_post([
            'post_title'   => 'Untitled Form',
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'meros-form',
        ]);

        $defaultMeta = [
            'schema' => [
                'settings' => [],
                'rows' => []
            ]
        ];

        FormMeta::create([
            'post_id' => $newFormId,
            'meta_key' => '_meros_form_meta',
            'meta_value' => json_encode($defaultMeta)
        ]);

        if (!is_int($newFormId)) {
            abort(500, 'Failed to create new form.');
        }

        $this->redirect(route('meros.toolbox.form-builder.edit', ['formID' => $newFormId]));
    }

    /**
     * Walk each field in the provided rows, including nested fields inside group rows.
     *
     * @param array    $rows
     * @param callable $callback Receives ($field, $location)
     *
     * @return void
     */
    private function walkFormFields(array $rows, callable $callback, bool $walkRepeaterFields = false): void {
        foreach ($rows as $rowIndex => $row) {
            if (($row['type'] ?? null) === 'fields') {
                foreach ($row['fields'] ?? [] as $fieldIndex => $field) {
                    $location = [
                        'rowIndex'      => $rowIndex,
                        'groupRowIndex' => null,
                        'fieldIndex'    => $fieldIndex,
                        'rowType'       => 'fields',
                    ];

                    $callback($field, $location);

                    if ($walkRepeaterFields && ($field['handle'] ?? null) === 'repeater') {
                        foreach ($field['fields'] ?? [] as $repeaterFieldIndex => $repeaterField) {
                            $callback($repeaterField, [
                                'rowIndex'           => $location['rowIndex'],
                                'groupRowIndex'      => $location['groupRowIndex'],
                                'fieldIndex'         => $location['fieldIndex'],
                                'rowType'            => $location['rowType'],
                                'repeaterFieldIndex' => $repeaterFieldIndex,
                            ]);
                        }
                    }
                }
            }

            if (($row['type'] ?? null) === 'group') {
                foreach ($row['group']['rows'] ?? [] as $groupRowIndex => $groupRow) {
                    foreach ($groupRow['fields'] ?? [] as $fieldIndex => $field) {
                        $location = [
                            'rowIndex'      => $rowIndex,
                            'groupRowIndex' => $groupRowIndex,
                            'fieldIndex'    => $fieldIndex,
                            'rowType'       => 'group',
                        ];

                        $callback($field, $location);

                        if ($walkRepeaterFields && ($field['handle'] ?? null) === 'repeater') {
                            foreach ($field['fields'] ?? [] as $repeaterFieldIndex => $repeaterField) {
                                $callback($repeaterField, [
                                    'rowIndex'           => $location['rowIndex'],
                                    'groupRowIndex'      => $location['groupRowIndex'],
                                    'fieldIndex'         => $location['fieldIndex'],
                                    'rowType'            => $location['rowType'],
                                    'repeaterFieldIndex' => $repeaterFieldIndex,
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Walk each group row in the provided rows, including nested group rows.
     *
     * @param array    $rows
     * @param callable $callback Receives ($group, $location)
     *
     * @return void
     */
    private function walkFormGroups(array $rows, callable $callback): void {
        foreach ($rows as $rowIndex => $row) {
            if (($row['type'] ?? null) === 'group') {
                $callback($row['group'] ?? [], [
                    'rowIndex' => $rowIndex,
                ]);
            }
        }
    }

    /**
     * Finds the first field matching a predicate.
     *
     * @param array    $rows
     * @param callable $predicate Receives ($field, $location)
     *
     * @return array|null
     */
    private function findFirstFormField(array $rows, callable $predicate): ?array {
        $match = null;

        $this->walkFormFields($rows, function($field, $location) use ($predicate, &$match) {
            if ($match !== null) {
                return;
            }

            if ($predicate($field, $location) === true) {
                $match = [
                    'field'    => $field,
                    'location' => $location,
                ];
            }
        });

        return $match;
    }
}