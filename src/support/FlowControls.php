<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;

use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Textarea;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\FlowActionsRepeater;
use MM\Meros\App\Components\Fields\RichText;


use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;
use MM\Meros\Facades\Automation\FlowActions;
use MM\Meros\Facades\Support\FormDynamicValues;

class FlowControls {
    /**
     * The Form CPT instance.
     *
     * @var FormHost
     */
    private FormHost $formHost;

    /**
     * The current form's ID.
     *
     * @var integer
     */
    private int $formId;

    /**
     * Array of registered form actions.
     *
     * @var array
     */
    private array $availableFlowActions;

    /**
     * The default dynamic options for the flow controls.
     *
     * @var array
     */
    private array $defaultDynamicOptions = [];

    /**
     * The constructed FlowControls instance used to configure the FlowAction in WP-Admin.
     *
     * @var FieldGroup
     */
    private FieldGroup $group;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Constructs a new FlowControls instance.
     *
     * @param FormHost  $formHost
     * @param array $defaultDynamicOptions
     */
    public function __construct(FormHost $formHost, array $defaultDynamicOptions = []) {
        $this->formHost = $formHost;
        $this->availableFlowActions = $this->getAvailableFlowActions();
        $this->defaultDynamicOptions = $this->getDefaultDynamicOptions($defaultDynamicOptions);
        $this->init();
    }

    /**
     * Initialises a FieldGroup instance to contain a Repeater field instance for configuring flow actions.
     *
     * @return void
     */
    private function init(): void {
        $group = FieldGroups::checkout(Framework::get())->get('form-actions');

        if ($group !== null) {
            $this->group = $group;
            return;
        }

        $this->group = FieldGroups::checkout(Framework::get())
         ->make(function (FieldGroup $group) {
            $group->id('form-actions');
            $group->title('Form Actions');
            $group->name('form_actions');
            $group->field('repeater', function (Repeater $repeater) {
                $repeater->name('form_actions');
                $repeater->label('Form Actions');
                $repeater->addRowText('Add Action');
                $repeater->hideLabel();

                $repeater->field('select', [
                    'name'    => 'action',
                    'label'   => 'Action',
                    'options' => $this->availableFlowActions
                ]);

                $repeater->field('hidden', function (Hidden $field) {
                    $field->name('action_unique_id');
                    $field->setValueWhen([
                        ['action', 'NOT EMPTY', null, '{value}_{row_index}']
                    ]);
                });

                $postId = $_GET['post'] ?? '';
                if (!is_string($postId)) {
                    $postId = '';
                }

                $repeater->field('hidden', [
                    'name'    => 'post_id',
                    'default' => $postId
                ]);

                $repeater->editForm([$this, 'getEditForm'], 'Configure', 'Edit Action', 'action');
            });
        });
    }

    /**
     * Returns the default dynamic options for the flow controls.
     *
     * @return array
     */
    private function getDefaultDynamicOptions(array $additionalOptions = []): array {
        return apply_filters('meros_flow_controls_dynamic_options', array_merge([
            'user_id' => [
                'name'      => 'user_id',
                'label'     => 'User: ID',
                'data_type' => 'integer'
            ],
            'user_display_name' => [
                'name'      => 'user_display_name',
                'label'     => 'User: Display Name',
                'data_type' => 'string'
            ],
            'form_id' => [
                'name'      => 'form_id',
                'label'     => 'Form: ID',
                'data_type' => 'integer'
            ],
            'form_response_id' => [
                'name'      => 'form_response_id',
                'label'     => 'Form Response: ID',
                'data_type' => 'integer'
            ]
        ], $additionalOptions));
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the constructed FieldGroup instance used to configure the FlowAction in WP-Admin.
     *
     * @return FieldGroup
     */
    public function getControls(): FieldGroup {
        return $this->group;
    }

    // =========================================================================
    // Configuration
    // =========================================================================

    /**
     * Returns the edit form for a specific flow action row.
     *
     * This function is used to configure the edit form for a flow action row in the repeater.
     * It populates the form with the current row data and handles dynamic values.
     *
     * @param FormComponent $formComponent
     * @param array         $rowData
     * @param array         $repeaterValue
     *
     * @return FormComponent
     */
    public function getEditForm(FormComponent $formComponent, array $rowData, array $repeaterValue): FormComponent {
        $actionName = $rowData['action'] ?? '';
        $actionId   = $rowData['action_unique_id'] ?? '';
        $postId     = $rowData['post_id'] ?? '';

        if ($postId === '') {
            return $formComponent;
        }

        if ($actionName === '' || !in_array($actionName, array_keys($this->availableFlowActions))) {
            return $formComponent;
        }

        $this->formId = (int) $postId;
        $registeredAction = FlowActions::getRegisteredFeature($actionName);

        if (!is_array($registeredAction) || 
            !array_key_exists('class', $registeredAction) || 
            !array_key_exists('initialised', $registeredAction)
        ) {
            return $formComponent;
        }

        $action = $this->getActionInstance($actionName);
        if (!$action) {
            throw new \RuntimeException("Cannot configure unregistered flow action '{$actionName}'.");
        }
        $formComponent->resolveDynamicValues(false);

        $formComponent->field('text', [
            'name'     => 'action_label',
            'label'    => 'Action Label',
            'required' => true
        ]);

        $formComponent->field('select', [
            'name'        => 'run_when',
            'label'       => 'Run When',
            'default'     => 'success',
            'description' => 'Uses the result of the last action that ran. Skipped actions do not change that result. The first action runs by default.',
            'options'     => [
                'success' => 'Previous Action Succeeded',
                'failure' => 'Previous Action Failed',
                'always'  => 'Always',
            ],
        ], false);

        $inputSchema = $action->getInputSchema();

        if ($inputSchema !== []) {
            $dynamicOptions = $this->buildDynamicOptions($actionId, $repeaterValue);
            $formFields = $this->formHost->getStoredFields($this->formId, [
                'list_type'     => 'with_type',
                'prepend_label' => 'Field: '
            ]) ?? [];

            $formComponent->section(function (FieldGroup $group) use ($formComponent, $inputSchema, $dynamicOptions, $formFields) {
                $group->title('Action Inputs');
                $group->description('Configure the inputs for this action.');
                $choices = [];
                foreach (FormDynamicValues::choices() as $option) {
                    $choices[$option['name']] = [
                        'name'  => $option['name'],
                        'label' => str_replace('_', ' ', $option['name']),
                        'type'  => $option['type'],
                        ...isset($option['field_type']) ? ['field_type' => $option['field_type']] : [],
                    ];
                }
                foreach (['dynamic' => $dynamicOptions, 'field' => $formFields] as $source => $options) {
                    foreach ($options as $option) {
                        if (($option['type'] ?? '') === 'password') continue;
                        if (!isset($option['name'], $option['label'], $option['data_type'])) continue;
                        $choices[$option['name']] = [
                            'name'   => $option['name'],
                            'label'  => $option['label'],
                            'type'   => $option['data_type'],
                            'source' => $source,
                            ...isset($option['field_type']) ? ['field_type' => $option['field_type']]
                                : (isset($option['type']) ? ['field_type' => $option['type']] : []),
                        ];
                    }
                }
                $choicesJson = json_encode(array_values($choices), JSON_THROW_ON_ERROR);
                $formComponent->attribute('data-flow-dynamic-choices', $choicesJson);

                $tableInputs = array_filter($inputSchema, fn (array $properties) => !FlowAction::inputUsesStandaloneField($properties));
                if ($tableInputs !== []) {
                    $group->field('flow_actions_repeater', function (FlowActionsRepeater $repeater) use ($tableInputs, $choicesJson) {
                        $repeater->name('action_inputs');
                        $repeater->label('Action Inputs');
                        $repeater->hideLabel(true);
                        $repeater->allowAdd(false);
                        $repeater->allowRemove(false);
                        $repeater->allowReorder(false);
                        $repeater->attribute('data-flow-dynamic-choices', $choicesJson);

                        $repeater->field('text', function ($field) {
                            $field->name('input_name');
                            $field->label('Input Name');
                            $field->readonly(true);
                        });

                        $repeater->field('text', [
                            'name'     => 'input_type',
                            'label'    => 'Input Type',
                            'readonly' => true
                        ]);

                        $repeater->field('text', [
                            'name'     => 'input_field_type',
                            'label'    => 'Input Field Type',
                            'readonly' => true
                        ]);

                        $repeater->field('checkbox', function (Checkbox $field) {
                            $field->name('use_dynamic_value');
                            $field->label('Use Dynamic Value');
                            $field->default(true);
                            $field->toggle(true);
                            $field->attribute('data-dynamic-selector', true);
                        });

                        $repeater->field('text', function ($field) {
                            $field->name('input_value');
                            $field->label('Input Value');
                        });

                        $repeaterValue = [];

                        foreach ($tableInputs as $inputName => $properties) {
                            $dataType = $properties['type'] ?? 'string';
                            $fieldType = $properties['field_type'] ?? FlowAction::inferInputFieldType($dataType);
                            $callback = $properties['field_callback'] ?? null;
                            if ($callback !== null && !is_callable($callback)) {
                                throw new \InvalidArgumentException("Invalid field callback for action input '{$inputName}'.");
                            }

                            $useDynamic = $this->usesDynamicDefault($properties);
                            $default = $properties['default'] ?? null;

                            if ($useDynamic && is_string($default) && !str_contains($default, '{{')) {
                                $default = '{{' . $default . '}}';
                            }

                            $staticField = $repeater->field($fieldType, function (Field $field) use ($properties, $inputName, $choicesJson) {
                                $this->configureInputField($field, 'input_value', 'Input Value', $inputName, $properties, $choicesJson, false);
                            }, false);

                            $repeater->inputField($inputName, $staticField,
                                ($properties['readonly'] ?? false) || !($properties['allow_dynamic_value'] ?? true),
                                $properties['required'] ?? false, $properties['readonly'] ?? false);

                            $repeaterValue[] = [
                                'input_name'        => $inputName,
                                'input_type'        => $dataType,
                                'input_field_type'  => $fieldType,
                                'use_dynamic_value' => $useDynamic,
                                'input_value'       => $default,
                            ];
                        }

                        $repeater->default($repeaterValue);
                    });
                }

                foreach ($inputSchema as $inputName => $properties) {
                    $fieldType = $properties['field_type'] ?? FlowAction::inferInputFieldType($properties['type'] ?? 'string');
                    if (!FlowAction::inputUsesStandaloneField($properties)) continue;
                    $callback = $properties['field_callback'] ?? null;
                    if ($callback !== null && !is_callable($callback)) {
                        throw new \InvalidArgumentException("Invalid field callback for action input '{$inputName}'.");
                    }
                    if ($fieldType === 'repeater' && $callback === null) {
                        throw new \InvalidArgumentException("A field callback is required for repeater input '{$inputName}'.");
                    }
                    $group->field($fieldType, function (Field $field) use ($inputName, $properties, $choicesJson) {
                        $this->configureInputField(
                            $field, Str::snake($inputName) . '_value', $properties['label'] ?? $inputName,
                            $inputName, $properties, $choicesJson, true
                        );
                    }, false);
                }
            });
        }

        $formComponent->section(function (FieldGroup $group) {
            $group->title('Error Handling');

            $group->row(function (FieldRow $row) {
                $row->field('select', [
                    'name'        => 'error_behaviour',
                    'label'       => 'Error Behaviour',
                    'description' => 'Continue allows later actions to handle a failure. Either Stop option ends the flow immediately.',
                    'default'     => 'continue',
                    'options'     => [
                        'continue'              => 'Continue',
                        'stop_and_show_error'   => 'Stop and Show Error',
                        'stop_and_show_success' => 'Stop and Show Success'
                    ]
                ]);
            });

            $group->row(function (FieldRow $row) {
                $row->field('textarea', function (TextArea $field) {
                    $field->name('error_message');
                    $field->label('Error Message');
                    $field->showWhen([
                        ['error_behaviour', '=', 'stop_and_show_error']
                    ]);
                });

                $row->field('textarea', function (TextArea $field) {
                    $field->name('success_message');
                    $field->label('Success Message');
                    $field->showWhen([
                        ['error_behaviour', '=', 'stop_and_show_success']
                    ]);
                });
            });
        });

        return $formComponent;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function configureInputField(
        Field $field, string $name, string $label, string $inputName, array $properties, string $choicesJson, bool $standalone
    ): void {
        $callback = $properties['field_callback'] ?? null;
        if ($callback !== null && !is_callable($callback)) {
            throw new \InvalidArgumentException("Invalid field callback for action input '{$inputName}'.");
        }

        $field->name($name);
        $field->label($label);
        if (($properties['description'] ?? null) !== null) $field->description($properties['description']);
        if ($field instanceof Select && is_array($properties['options'] ?? null)) $field->options($properties['options']);
        if ($field instanceof Select && ($properties['type'] ?? null) === 'array.scalar') $field->multiple();
        if (array_key_exists('default', $properties) && ($standalone || !$this->usesDynamicDefault($properties))) {
            $field->default($properties['default']);
        }
        if ($properties['required'] ?? false) $field->required();
        if ($properties['readonly'] ?? false) $field->readonly();
        if ($callback !== null) $callback($field);
        $field->name($name);
        if ($field instanceof RichText) {
            if (($properties['allow_dynamic_value'] ?? true) === true) {
                $field->attribute('data-flow-dynamic-choices', $choicesJson);
            } else {
                $field->removeAttribute('data-flow-dynamic-choices');
            }
        }
    }

    /**
     * Helper method to retrieve an instance of a registered FlowAction by its name.
     *
     * @param string $name
     *
     * @return FlowAction|null
     */
    private function getActionInstance(string $name): ?FlowAction {
        $registeredAction = FlowActions::getRegisteredFeature($name);

        if (!is_array($registeredAction) || 
            !array_key_exists('provider', $registeredAction) ||
            !array_key_exists('class', $registeredAction) || 
            !array_key_exists('initialised', $registeredAction)
        ) {
            return null;
        }

        $provider = $registeredAction['provider'];

        if (!($provider instanceof FeatureProvider)) {
            return null;
        }

        $class = $registeredAction['class'];
        $initialised = $registeredAction['initialised'];

        if (!$initialised) {
            $action = FlowActions::checkout($provider->get())
                ->makeFrom($class);
        } else {
            $action = FlowActions::get($name);
        }

        if (!($action instanceof FlowAction)) {
            return null;
        }

        return $action;
    }

    /**
     * Builds dynamic options for a form action based on the output schema of previously configured actions within the repeater.
     *
     * @param string $actionId
     * @param array  $repeaterValue
     *
     * @return array
     */
    private function buildDynamicOptions(string $actionId, array $repeaterValue): array {
        $options = $this->defaultDynamicOptions;

        if ($repeaterValue !== []) {
            foreach ($repeaterValue as $row => $rowValue) {
                $prevActionName = $rowValue['action'] ?? '';
                $prevActionId   = $rowValue['action_unique_id'] ?? '';
                
                if ($prevActionName === '' || $prevActionId === '') {
                    continue;
                }

                if ($prevActionId === $actionId) {
                    break;
                }

                if (!array_key_exists('form_data', $rowValue) || !is_array($rowValue['form_data'])) {
                    continue;
                }

                $prevAction = $this->getActionInstance($prevActionName);

                if (!($prevAction instanceof FlowAction)) {
                    continue;
                }

                $inputSchema = $prevAction->getInputSchema();
                $settings = $rowValue['form_data'];
                if (array_key_exists('await_response', $inputSchema)) {
                    $awaitRow = FlowAction::inputUsesStandaloneField($inputSchema['await_response'])
                        ? ['input_value' => $settings['await_response_value'] ?? $inputSchema['await_response']['default'] ?? true]
                        : collect($settings['action_inputs'] ?? [])->first(
                            fn ($input) => is_array($input) && ($input['input_name'] ?? null) === 'await_response'
                        );
                    if (($awaitRow['use_dynamic_value'] ?? false) !== true &&
                        ($awaitRow['input_value'] ?? $inputSchema['await_response']['default'] ?? true) === false) {
                        continue;
                    }
                }

                $prevActionOutputSchema = $prevAction->getOutputSchema();

                if ($prevActionOutputSchema !== []) {
                    foreach ($prevActionOutputSchema as $outputName => $config) {
                        if (!array_key_exists('type', $config) || !is_string($config['type'])) {
                            continue;
                        }

                        $options[$outputName . '_source_' . $prevActionId] = [
                            'name'      => $outputName . '_source_' . $prevActionId,
                            'label'     => ('Action Output: ' . ($rowValue['form_data']['action_label'] ?? $prevActionName)) . ' - ' . $outputName,
                            'data_type' => $config['type'],
                            ...isset($config['field_type']) ? ['field_type' => $config['field_type']] : [],
                        ];
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Retrieves a property value from the input properties array, ensuring it matches the expected type.
     *
     * @param string $prop
     * @param string $type
     * @param mixed  $default
     * @param array  $properties
     *
     * @return mixed
     */
    private function getInputProperty(string $prop, string $type, mixed $default, array $properties): mixed {
        return array_key_exists($prop, $properties) && gettype($properties[$prop]) === $type
            ? $properties[$prop]
            : $default;
    }

    /**
     * Determines if an input should use a dynamic default value based on its properties.
     *
     * @param array $properties
     *
     * @return bool
     */
    private function usesDynamicDefault(array $properties): bool {
        return $this->getInputProperty('allow_dynamic_value', 'boolean', true, $properties)
            && $this->getInputProperty('dynamic', 'boolean',
                !$this->getInputProperty('readonly', 'boolean', false, $properties)
                    && !array_key_exists('default', $properties),
                $properties
            );
    }

    /**
     * Builds the configuration row for a specific input of a form action within action repeater row.
     *
     * @param FieldRow   $row
     * @param string     $inputName
     * @param array      $properties
     *
     * @return void
     */
    private function buildInputConfigRow(FieldRow $row, string $inputName, array $properties): void {
        $dataType = $properties['type'] ?? null;
        $fieldName = Str::snake($inputName);

        if ($dataType === null || !is_string($dataType) || empty($dataType)) {
            return;
        }

        $fieldType      = $this->getInputProperty('field_type', 'string', null, $properties);
        $readonly       = $this->getInputProperty('readonly', 'boolean', false, $properties);
        $dynamicAllowed = $this->getInputProperty('allow_dynamic_value', 'boolean', true, $properties);
        $useDynamic     = $this->usesDynamicDefault($properties);

        if ($fieldType === null) {
            $fieldType = FlowAction::inferInputFieldType($dataType);
        }

        if ($dataType === 'array') {
            $dataType = 'array.scalar';
        }

        $row->field('text', [
            'name'     => $fieldName . '_label',
            'label'    => 'Input API Name',
            'default'  => $inputName,
            'readonly' => true,
        ]);

        $row->field('checkbox', function (Checkbox $field) use ($fieldName, $dynamicAllowed, $useDynamic, $readonly) {
            $field->name('use_dynamic_value_' . $fieldName);
            $field->label('Use Dynamic Value');
            $field->default($useDynamic);
            $field->vertical();

            if ($readonly || !$dynamicAllowed) {
                $field->disabled();
            }
        });
    }

    /**
     * Builds the value row for a specific input of a form action within the configuration section.
     *
     * @param FieldRow $row
     * @param string   $inputName
     * @param array    $properties
     *
     * @return void
     */
    private function buildInputValueRow(FieldRow $row, string $inputName, array $properties, array $dynamicOptions, array $formFields): void {
        $dataType = $properties['type'] ?? null;
        $fieldName = Str::snake($inputName);

        if ($dataType === null || !is_string($dataType) || empty($dataType)) {
            return;
        }
        
        $fieldType      = $this->getInputProperty('field_type', 'string', null, $properties);
        $required       = $this->getInputProperty('required', 'boolean', false, $properties);
        $readonly       = $this->getInputProperty('readonly', 'boolean', false, $properties);
        $dynamicAllowed = $this->getInputProperty('allow_dynamic_value', 'boolean', true, $properties);
        $useDynamic = $this->usesDynamicDefault($properties);
        $options        = $this->getInputProperty('options', 'array', [], $properties);

        if ($fieldType === null) {
            $fieldType = FlowAction::inferInputFieldType($dataType);
        }

        if (!$readonly || !$useDynamic) {
            $row->field($fieldType, function($field) use($dataType, $fieldType, $fieldName, $options, $required, $readonly, $dynamicAllowed, $useDynamic, $properties) {
                $field->name($fieldName . '_value');
                $field->label('Value');
                $field->readonly($readonly);

                if (!$useDynamic && array_key_exists('default', $properties)) {
                    $field->default($properties['default']);
                }

                if ($dynamicAllowed && !$readonly) {
                    $field->showWhen([
                        ['use_dynamic_value_' . $fieldName, '=', false]
                    ]);
                }

                if ($required) {
                    $field->required();
                }

                if ($fieldType === 'select' && $options !== []) {
                    $field->options($options);
                }

                if ($fieldType === 'select' && $dataType === 'array.scalar') {
                    $field->multiple(true);
                }
            });
        }

        if ($dynamicAllowed && (!$readonly || $useDynamic)) {
            $row->field('select', function(Select $field) use($dataType, $fieldName, $dynamicOptions, $formFields, $required, $readonly, $useDynamic, $properties) {
                $field->name($fieldName . '_dynamic_value');
                $field->label('Value');

                $isCompatible = fn ($option) => is_string($option['data_type'] ?? null) && array_diff(
                    explode('|', $option['data_type']),
                    explode('|', $dataType)
                ) === [];
                $dynamicOptions = array_merge(
                    collect($dynamicOptions)->filter($isCompatible)
                        ->pluck('label', 'name')
                        ->toArray(),
                    collect($formFields)->filter($isCompatible)
                        ->pluck('label', 'name')
                        ->toArray()
                );

                if ($useDynamic && array_key_exists('default', $properties)) {
                    $field->default($properties['default']);
                }

                if ($readonly) {
                    $default = $properties['default'] ?? null;
                    if (!is_string($default) || !array_key_exists($default, $dynamicOptions)) {
                        throw new \InvalidArgumentException("Invalid locked dynamic default for action input '{$fieldName}'.");
                    }
                    // A single option stays submitted, unlike a disabled select.
                    $dynamicOptions = [$default => $dynamicOptions[$default]];
                    $field->readonly();
                } else {
                    $field->showWhen([
                        ['use_dynamic_value_' . $fieldName, '=', true]
                    ]);
                }
                $field->options($dynamicOptions);

                if ($required) {
                    $field->required();
                }
            });
        }
    }

    /**
     * Infers the field type based on the data type of the item.
     *
     * @return string The inferred field type.
     */
    /**
     * Helper to retrieve available form actions for the form.
     *
     * @return array
     */
    private function getAvailableFlowActions(): array {
        return collect(FlowActions::getRegisteredFeatures())
            ->keyBy('alias')
            ->mapWithKeys(function ($action, $alias) {
                $label = Str::title(Str::replace('_', ' ', $alias));

                if ($action['initialised'] ?? false) {
                    $instance = FlowActions::get($alias);

                    if ($instance !== null) {
                        $label = $instance->getLabel();
                    }
                }

                return [$alias => $label];
            })
            ->all();
    }
}