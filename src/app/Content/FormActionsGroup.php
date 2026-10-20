<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;
use MM\Meros\Contracts\Features\Automation\FlowAction;

use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Textarea;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Components\FieldGroups;
use MM\Meros\Facades\Automation\FlowActions;

final class FormActionsGroup {
    /**
     * The Form CPT instance.
     *
     * @var Form
     */
    private Form $form;

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
     * The constructed FieldGroup instance used to configure the FlowAction in WP-Admin.
     *
     * @var FieldGroup
     */
    private FieldGroup $group;

    public function __construct(Form $form) {
        $this->form = $form;
        $this->availableFlowActions = $this->getAvailableFlowActions();
        $this->init();
    }

    /**
     * Initialises a FieldGroup instance to contain a Repeater field instance for configuring form actions.
     *
     * @return void
     */
    private function init(): void {
        $this->group = FieldGroups::checkout(Framework::get())
         ->make(function (FieldGroup $group) {
            $group->title('Form Actions');
            $group->name('form_actions');
            $group->field('repeater', function (Repeater $repeater) {
                $repeater->name('form_actions');
                $repeater->label('Form Actions');

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

                $repeater->field('hidden', [
                    'name'    => 'post_id',
                    'default' => $_GET['post'] ?? ''
                ]);

                $repeater->editForm([$this, 'getEditForm'], 'Configure', 'Edit Action');
            });
        });
    }

    /**
     * Returns the constructed FieldGroup instance used to configure the FlowAction in WP-Admin.
     *
     * @return FieldGroup
     */
    public function getGroup(): FieldGroup {
        return $this->group;
    }

    /**
     * Returns the edit form for a specific form action within the repeater.
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

        $formComponent->field('text', [
            'name'     => 'action_label',
            'label'    => 'Action Label',
            'required' => true
        ]);

        $inputSchema = $action->getInputSchema();

        if ($inputSchema !== []) {
            $dynamicOptions = $this->buildDynamicOptions($actionId, $repeaterValue);
            $formFields = $this->form->getStoredFields($this->formId, [
                'list_type'     => 'with_type',
                'prepend_label' => 'Field: '
            ]) ?? [];

            $formComponent->section(function (FieldGroup $group) use ($inputSchema, $dynamicOptions, $formFields) {
                $group->title('Action Inputs');

                foreach ($inputSchema as $inputName => $properties) {
                    $group->row(function (FieldRow $row) use ($inputName, $properties) {
                        $this->buildInputConfigRow($row, $inputName, $properties);
                    });

                    $group->row(function (FieldRow $row) use ($inputName, $properties, $dynamicOptions, $formFields) {
                        $this->buildInputValueRow($row, $inputName, $properties, $dynamicOptions, $formFields);
                    });
                }
            });
        }

        $formComponent->section(function (FieldGroup $group) {
            $group->title('Error Handling');

            $group->row(function (FieldRow $row) {
                $row->field('select', [
                    'name'    => 'error_behaviour',
                    'label'   => 'Error Behaviour',
                    'default' => 'continue',
                    'options' => [
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
        $options = [
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
        ];

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

                $prevActionOutputSchema = $prevAction->getOutputSchema();

                if ($prevActionOutputSchema !== []) {
                    foreach ($prevActionOutputSchema as $outputName => $config) {
                        if (!array_key_exists('type', $config) || !is_string($config['type'])) {
                            continue;
                        }

                        $options[$outputName . '_source_' . $prevActionId] = [
                            'name'      => $outputName . '_source_' . $prevActionId,
                            'label'     => ('Action Output: ' . ($rowValue['form_data']['action_label'] ?? $prevActionName)) . ' - ' . $outputName,
                            'data_type' => $config['type']
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

        if ($dataType === null || !is_string($dataType) || empty($dataType)) {
            return;
        }

        $fieldType      = $this->getInputProperty('field_type', 'string', null, $properties);
        $dynamicAllowed = $this->getInputProperty('allow_dynamic_value', 'boolean', true, $properties);

        if ($fieldType === null) {
            $fieldType = $this->inferFieldType($dataType);
        }

        if ($dataType === 'array') {
            $dataType = 'array.scalar';
        }

        $row->field('text', [
            'name'     => $inputName . '_label',
            'label'    => 'Input API Name',
            'default'  => $inputName,
            'readonly' => true,
        ]);

        $row->field('checkbox', function ($field) use ($inputName, $dynamicAllowed) {
            $field->name('use_dynamic_value_' . $inputName);
            $field->label('Use Dynamic Value');
            $field->default($dynamicAllowed);

            if ($dynamicAllowed === false) {
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
        $dataType       = $properties['type'] ?? null;

        if ($dataType === null || !is_string($dataType) || empty($dataType)) {
            return;
        }
        
        $fieldType      = $this->getInputProperty('field_type', 'string', null, $properties);
        $required       = $this->getInputProperty('required', 'boolean', false, $properties);
        $dynamicAllowed = $this->getInputProperty('allow_dynamic_value', 'boolean', true, $properties);
        $options        = $this->getInputProperty('options', 'array', [], $properties);

        if ($fieldType === null) {
            $fieldType = $this->inferFieldType($dataType);
        }

        $row->field($fieldType, function($field) use($dataType, $fieldType, $inputName, $options, $required, $dynamicAllowed) {
            $field->name($inputName . '_value');
            $field->label('Value');

            if ($dynamicAllowed) {
                $field->showWhen([
                    ['use_dynamic_value_' . $inputName, '=', false]
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

        if ($dynamicAllowed) {
            $row->field('select', function(Select $field) use($dataType, $inputName, $dynamicOptions, $formFields, $required) {
                $field->name($inputName . '_dynamic_value');
                $field->label('Value');

                $dynamicOptions = array_merge(
                    collect($dynamicOptions)->where('data_type', $dataType)
                        ->pluck('label', 'name')
                        ->toArray(),
                    collect($formFields)->where('data_type', $dataType)
                        ->pluck('label', 'name')
                        ->toArray()
                );

                $field->options($dynamicOptions);
                $field->showWhen([
                    ['use_dynamic_value_' . $inputName, '=', true]
                ]);

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
    private function inferFieldType(string $dataType): string {
        return match ($dataType) {
            'array.object'      => 'repeater',
            'array.scalar'      => 'multi_select',
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            default             => 'text'
        };
    }

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