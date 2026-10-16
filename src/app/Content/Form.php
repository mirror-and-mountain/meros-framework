<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;
use Livewire\Livewire;

use MM\Meros\App\Models\Post;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\FormAction;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Components\Fields;
use MM\Meros\Facades\Components\FormActions;

use Illuminate\Support\Facades\Log;

class Form extends PostType {

    // =========================================================================
    // Configuration
    // =========================================================================

    protected function configure(): void {
        $this->public();
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');

        add_action('meros_framework_booted', function () {
            $this->configureBuilderMetabox();
            $this->configureFormActionsFields();
        });

        // Sets the render callback for form post types, filtering 'the content'
        // to render the form using its stored strucure/
        $this->render(function (string $content, int $postId, Post $post): string {
            $structure = $this->getFormStructure($postId);

            if ($structure === null) {
                return $content;
            }

            $rows = $structure['rows'] ?? [];

            if (empty($rows)) {
                return $content;
            }

            $form = $this->makeFormInstance($post, $rows, [], is_admin());
            
            if (!($form instanceof FormComponent)) {
                return $content;
            }

            $form = apply_filters("meros_render_form_{$postId}", $form);
            return $form->html();
        });
    }

    public function inspect(?int $postId = null, bool $refresh = false): array {
        $data = parent::inspect($postId, $refresh);

        if (array_key_exists('_meros_form_structure', $data) && is_string($data['_meros_form_structure'])) {
            $data['_meros_form_structure'] = unserialize($data['_meros_form_structure']);
        }

        return $data;
    }

    // =========================================================================
    // Form Builder
    // =========================================================================

    /**
     * Configures the form builder meta box on the form edit screen.
     *
     * @return void
     */
    private function configureBuilderMetabox(): void {
        $componentTemplate = $this->makeComponentTemplate();
        $this->addSaveAction();

        $this->metaBox('form_builder', 'Form Builder', function (\WP_Post $post) use ($componentTemplate): void {
            $formData = $this->getFormStructure($post->ID);

            if ($formData === null) {
                $formData = [];
            }

            echo Livewire::mount('meros::form-builder', [
                'formData'          => $formData, 
                'componentTemplate' => $componentTemplate
            ]);
        });
    }

    /**
     * Configures the meta box used to create form actions.
     *
     * @return void
     */
    private function configureFormActionsFields(): void {
        $this->fields(function (FieldGroup $group) {
            $group->title('Form Actions');
            
            $group->field('repeater', function (Repeater $repeater) {
                $repeater->name('form_actions');
                $repeater->label('Form Actions');

                $actions = $this->getAvailableFormActions();

                $repeater->field('select', [
                    'name'    => 'action',
                    'label'   => 'Action',
                    'options' => $actions
                ]);

                $repeater->field('hidden', [
                    'name'    => 'post_id',
                    'default' => $_GET['post'] ?? ''
                ]);

                $repeater->editForm(function(FormComponent $form, array $rowData, array $repeaterValue) use($actions): FormComponent {
                    $action = $rowData['action'] ?? '';
                    $postId = $rowData['post_id'] ?? '';

                    if ($postId === '') {
                        return $form;
                    }

                    if ($action === '' || !in_array($action, array_keys($actions))) {
                        return $form;
                    }

                    $actionClass = FormActions::getRegisteredFeatureClass($action);

                    if (!is_string($actionClass) || !class_exists($actionClass)) {
                        return $form;
                    }

                    $form->field('text', [
                        'name'     => 'action_label',
                        'label'    => 'Action Label',
                        'required' => true
                    ]);

                    $inputSchema = $actionClass::getInputSchema();

                    if ($inputSchema !== []) {
                        $dynamicOptions = [
                            ''                  => 'Select...',
                            'user_id'           => 'User: ID',
                            'user_display_name' => 'User: Display Name',
                            'user_first_name'   => 'User: First Name',
                            'user_last_name'    => 'User: Last Name',
                            'user_email'        => 'User: Email'
                        ];

                        $formFields = $this->getStoredFields((int) $postId, [
                            'list_type'     => 'with_type',
                            'prepend_label' => 'Field: '
                        ]) ?? [];

                        if ($repeaterValue !== []) {
                            foreach ($repeaterValue as $row => $rowValue) {
                                $prevAction = $rowValue['action'] ?? '';
                                
                                if ($prevAction === '') {
                                    continue;
                                }

                                if ($prevAction === $action) {
                                    break;
                                }

                                $prevActionClass = FormActions::getRegisteredFeatureClass($prevAction);

                                if (!is_string($prevActionClass) || !class_exists($prevActionClass)) {
                                    continue;
                                }

                                $prevActionOutputSchema = $prevActionClass::getOutputSchema();

                                if ($prevActionOutputSchema !== []) {
                                    foreach ($prevActionOutputSchema as $outputName => $config) {
                                        $dynamicOptions[$outputName] = ('Action Output: ' . $rowValue['form_data']['action_label'] ?? $prevAction) . ' - ' . $outputName;
                                    }
                                }
                            }
                        }

                        foreach ($inputSchema as $name => $properties) {
                            $type = $properties['type'] ?? null;

                            if ($type === null || !is_string($type)) {
                                continue;
                            }

                            $getInputProp = function (string $prop, string $type, mixed $default) use($properties): mixed {
                                $prop = array_key_exists($prop, $properties) && gettype($properties[$prop] === $type)
                                    ? $properties[$prop]
                                    : $default;

                                return $prop;
                            };

                            $fieldType      = $getInputProp('field_type', 'string', null);
                            $required       = $getInputProp('required', 'boolean', false);
                            $dynamicAllowed = $getInputProp('allow_dynamic_value', 'boolean', true);
                            $label          = $getInputProp('label', 'string', null);
                            $description    = $getInputProp('description', 'string', null);
                            $options        = $getInputProp('options', 'array', []);

                            if ($fieldType === null) {
                                switch($type) {
                                    case 'string':
                                        $fieldType = 'text';
                                        break;
                                    case 'integer':
                                        $fieldType = 'number';
                                        break;
                                    case 'boolean':
                                        $fieldType = 'checkbox';
                                        break;
                                    case 'array':
                                        $fieldType = 'select';
                                        $type = 'array.scalar';
                                        break;
                                }
                            }

                            $form->section(function(FieldGroup $section) use(
                                $name, 
                                $type,
                                $fieldType,
                                $required, 
                                $dynamicAllowed, 
                                $dynamicOptions, 
                                $options, 
                                $label, 
                                $description,
                                $formFields
                            ) {
                                if ($label !== null) {
                                    $section->title($label);
                                }

                                if ($description !== null) {
                                    $section->description($description);
                                }

                                $section->row(function(FieldRow $row) use($name, $dynamicAllowed) {
                                    $row->field('text', [
                                        'name'     => $name . '_label',
                                        'label'    => 'Input API Name',
                                        'default'  => $name,
                                        'readonly' => true,
                                    ]);

                                    $row->field('checkbox', function ($field) use ($name, $dynamicAllowed) {
                                        $field->name('use_dynamic_value_' . $name);
                                        $field->label('Use Dynamic Value');
                                        $field->default($dynamicAllowed);

                                        if ($dynamicAllowed === false) {
                                            $field->disabled();
                                        }
                                    });
                                });

                                $section->row(function(FieldRow $row) use($type, $fieldType, $name, $dynamicOptions, $formFields, $options, $required) {
                                    $row->field($fieldType, function($field) use($type, $fieldType, $name, $options, $required) {
                                        $field->name($name . '_value');
                                        $field->label('Value');
                                        $field->showWhen([
                                            ['use_dynamic_value_' . $name, '=', false]
                                        ]);

                                        if ($required) {
                                            $field->required();
                                        }

                                        if ($fieldType === 'select' && $options !== []) {
                                            $field->options($options);
                                        }

                                        if ($fieldType === 'select' && $type === 'array.scalar') {
                                            $field->multiple(true);
                                        }
                                    });

                                    $row->field('select', function(Select $field) use($type, $name, $dynamicOptions, $formFields, $required) {
                                        $field->name($name . '_dynamic_value');
                                        $field->label('Value');

                                        $dynamicOptions = array_merge($dynamicOptions, 
                                            collect($formFields)->where('data_type', $type)
                                                ->pluck('label', 'name')
                                                ->toArray()
                                        );

                                        $field->options($dynamicOptions);
                                        $field->showWhen([
                                            ['use_dynamic_value_' . $name, '=', true]
                                        ]);

                                        if ($required) {
                                            $field->required();
                                        }
                                    });
                                });
                            });
                        }
                    }

                    return $form;
                }, 'Configure', 'Action Inputs');
            });
        });
    }

    /**
     * Creates a field or fieldgroup component editor template for the form builder.
     * These take the form of a repeater field.
     *
     * @return Repeater
     */
    private function makeComponentTemplate(): Repeater {
        return Fields::checkout($this->getProvider())
            ->makeFrom('repeater', function (Repeater $repeater) {
                $repeater->name('form_component');
                $repeater->label('Form Component');
                $repeater->allowAdd(false);
                $repeater->allowRemove(false);
                $repeater->allowReorder(false);
                $repeater->hideLabel(true);

                $repeater->field('select', [
                    'name'    => 'field_type',
                    'label'   => 'Field Type',
                    'options' => $this->getAvailableFieldTypes(),
                    'default' => 'text'
                ]);

                $repeater->field('text', [
                    'name' => 'field_label',
                    'label' => 'Field Label'
                ]);

                $repeater->field('hidden', [
                    'name' => 'field_name'
                ]);

                $repeater->field('hidden', [
                    'name' => 'field_description'
                ]);

                $repeater->editForm(function (FormComponent $form, array $rowData): FormComponent {
                    $fieldType = $rowData['field_type'] ?? '';
                    
                    if (empty($fieldType)) {
                        return $form;
                    }

                    $fieldClass = Fields::getRegisteredFeatureClass($fieldType);

                    if (!is_string($fieldClass) || !class_exists($fieldClass)) {
                        return $form;
                    }

                    $controls = $fieldClass::getBuilderControls();

                    if ($controls instanceof FieldGroup) {
                        $form->section($controls);
                    }

                    return $form;
                }, 'Configure', 'Edit Field');
            });
    }

    // =========================================================================
    // Form Builder - Config Persistance
    // =========================================================================

    /**
     * Configures the save action for the form cpt to save the form's structural data as post meta.
     *
     * @return void
     */
    private function addSaveAction(): void {
        add_action('save_post_' . $this->handle, function (int $postId) {
            // if (!isset($_POST['_meros_form_builder_nonce']) || 
            //     !wp_verify_nonce($_POST['_meros_form_builder_nonce'], 'meros_form_structure_save')) {
            //     return;
            // }

            if (!current_user_can('edit_post', $postId)) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!isset($_POST['form_components']) || !is_array($_POST['form_components'])) {
                return;
            }

            $formStructure = wp_unslash($_POST['form_components']);
            $cleanRows  = [];
            $groupIds   = [];
            $fieldNames = [];
            $groupNames = [];

            foreach ($formStructure as $rowData) {
                if (!is_array($rowData)) {
                    continue;
                }

                $cleanRow = [];
                $fields   = $this->getSubmittedBuilderValue($rowData, 'fields', []);
                $group    = $this->getSubmittedBuilderValue($rowData, 'childGroup');

                if (is_array($group) && $group !== []) {
                    $cleanRow['childGroup'] = $this->parseSubmittedFieldGroup($group, $groupIds, $fieldNames, $groupNames);
                } elseif (is_array($fields)) {
                    $cleanRow['fields'] = $this->parseSubmittedFieldComponents($fields, $fieldNames);
                }

                if ($cleanRow !== []) {
                    $cleanRows[] = $cleanRow;
                }
            }

            $formInstance = $this->makeFormInstance(Post::find($postId), $cleanRows);
            $serializedStructure = $formInstance->serialize('storage');

            update_post_meta($postId, '_meros_form_structure', $serializedStructure);
        });
    }

    /**
     * Helper to retrieve a value from the form's submitted save data.
     *
     * @param array  $data
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    private function getSubmittedBuilderValue(array $data, string $key, mixed $default = null): mixed {
        foreach ([$key, "'{$key}'"] as $candidate) {
            if (array_key_exists($candidate, $data)) {
                return $data[$candidate];
            }
        }

        return $default;
    }

    /**
     * Parses the form's field components for storage.
     *
     * @param array $components
     * @param array $fieldNames
     *
     * @return array
     */
    private function parseSubmittedFieldComponents(array $components, array &$fieldNames): array {
        $fields = [];
        $collectFields = function (array $data, int|string|null $rowIndex = null) use (&$collectFields, &$fields, &$fieldNames): void {
            if ((string) $rowIndex === '-1') {
                return;
            }

            if (
                is_string($data['field_type'] ?? null)
                && $data['field_type'] !== ''
                && is_string($data['field_name'] ?? null)
                && $data['field_name'] !== ''
                && $data['field_name'] !== '__FIELD_NAME__'
            ) {
                $name = $data['field_name'];
                if (preg_match('/^new_field_\d+$/', $name) === 1) {
                    $name = $this->makeUniqueGeneratedName('field', $fieldNames);
                } else {
                    $fieldNames[$name] = true;
                }

                $fieldSpecificSettings = $data['__form_data'] ?? '';
                $decodedSettings = [];

                if (is_string($fieldSpecificSettings) && !empty($fieldSpecificSettings)) {
                    $settings = json_decode($fieldSpecificSettings, true);
                    $decodedSettings = is_array($settings) ? $settings : [];
                    unset(
                        $decodedSettings['type'],
                        $decodedSettings['name'],
                        $decodedSettings['label'],
                        $decodedSettings['description']
                    );
                }

                $fields[] = [
                    'type'        => $data['field_type'],
                    'name'        => $name,
                    'label'       => is_string($data['field_label'] ?? null) ? $data['field_label'] : '',
                    'description' => is_string($data['field_description'] ?? null) ? $data['field_description'] : '',
                    ...$decodedSettings
                ];

                return;
            }

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $collectFields($value, is_int($key) || is_string($key) ? $key : null);
                }
            }
        };

        $collectFields($components);

        return $fields;
    }

    /**
     * Parses the form's group/section components for storage.
     *
     * @param array $group
     * @param array $groupIds
     * @param array $fieldNames
     * @param array $groupNames
     *
     * @return array
     */
    private function parseSubmittedFieldGroup(array $group, array &$groupIds, array &$fieldNames, array &$groupNames): array {
        $rows = $this->getSubmittedBuilderValue($group, 'rows', []);
        $groupRows = [];
        $availableTypes = $this->getAvailableFieldTypes();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $fields = $this->getSubmittedBuilderValue($row, 'fields', []);
                $definitions = [];

                if (is_array($fields)) {
                    foreach ($this->parseSubmittedFieldComponents($fields, $fieldNames) as $field) {
                        $type = $field['type'];

                        if (!array_key_exists($type, $availableTypes)) {
                            $type = Str::replace('_', '-', $type);
                        }

                        if (!array_key_exists($type, $availableTypes)) {
                            continue;
                        }

                        $field['type'] = $type;
                        $definitions[] = $field;
                    }
                }

                $groupRows[] = ['fields' => $definitions];
            }
        }

        $name = $this->getSubmittedBuilderValue($group, 'name');
        $title = $this->getSubmittedBuilderValue($group, 'title');
        $description = $this->getSubmittedBuilderValue($group, 'description');
        $id = $this->getSubmittedBuilderValue($group, 'id');
        $id = is_string($id) && $id !== ''
            ? Str::slug(Str::replace('_', '-', $id))
            : null;

        if ($id === null || $id === 'group' || isset($groupIds[$id])) {
            do {
                $id = 'mforms-section-' . Str::uuid();
            } while (isset($groupIds[$id]));
        }

        $groupIds[$id] = true;

        $name = is_string($name) && $name !== '' && $name !== 'group'
            ? $name
            : '';
        if ($name === '' || preg_match('/^new_group(?:_\d+)?$/', $name) === 1) {
            $name = $this->makeUniqueGeneratedName('group', $groupNames);
        } else {
            $groupNames[$name] = true;
        }

        $definition = [
            'name'        => $name,
            'title'       => is_string($title) ? $title : 'Group',
            'description' => is_string($description) ? $description : '',
            'rows'        => $groupRows,
            'id'          => $id,
        ];

        return $definition;
    }

    /**
     * Helper to generate unique names for fields.
     *
     * @param string $prefix
     * @param array  $usedNames
     *
     * @return string
     */
    private function makeUniqueGeneratedName(string $prefix, array &$usedNames): string {
        do {
            $name = $prefix . '_' . Str::substr(Str::uuid(), 0, 8);
        } while (isset($usedNames[$name]));

        $usedNames[$name] = true;

        return $name;
    }

    // =========================================================================
    // Submission Handling
    // =========================================================================

    public function handleSubmission(int $formId, array|string $data): string|array {
        $form = Post::find($formId);

        if ($form === null) {
            return 'Unable to locate form: Null Form Model.';
        }

        $instance = $this->makeFormInstance($form);
        if (!($instance instanceof FormComponent)) {
            return 'Unable to parse form.';
        }

        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }

        $actions = $this->getFormActions($formId);
        $payload = $instance->buildSubmissionPayload($data);

        $result = $this->processFormActions($formId, $payload, $actions);

        if (is_string($result)) {
            return 'Error running form actions.';
        }

        return [
            'payload' => $payload,
            'actions' => $actions
        ];
    }

    private function processFormActions(int $formId, array $payload, array $actions): string|true {
        $previousActionOutputs = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                return 'Action is not configured correctly.';
            }
            
            $name          = $action['action'] ?? '';
            $configuration = $action['__form_data'] ?? '';
            $class         = FormActions::getRegisteredFeatureClass($name);

            if (!is_string($class) || !class_exists($class)) {
                return 'Action ' . $name . ' is not configured correctly.';
            }

            if (is_string($configuration) && $configuration !== '') {
                $configuration = json_decode($configuration, true);
            } else {
                $configuration = [];
            }

            if ($previousActionOutputs !== []) {
                $payload['prev_action_outputs'] = $previousActionOutputs;
            }

            $result = $this->runFormAction($formId, $class, $configuration, $payload);

            Log::info($name, [
                'result' => $result
            ]);

            if (is_array($result)) {
                $previousActionOutputs[$name] = $result;
            }
        }

        return true;
    }

    private function runFormAction(int $formId, string $class, array $configuration, array $payload): mixed {
        $action = FormActions::checkout($this->getProvider())
            ->makeFrom($class);

        if (!($action instanceof FormAction)) {
            return false;
        }

        return $action->run($formId, $configuration, $payload)->output();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a Form instance using the form's structure.
     *
     * @param Post    $post
     * @param array   $rows
     * @param array   $availableFields
     * @param boolean $hideSubmitButton
     *
     * @return FormComponent
     */
    public function makeFormInstance(Post $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
        $rows = empty($rows) ? $this->getFormStructure($post->ID)['rows'] ?? [] : $rows;
        $availableFields = empty($availableFields) ? $this->getAvailableFieldTypes() : $availableFields;

        $form = Forms::checkout($this->getProvider())->make(function (FormComponent $form) use ($post, $rows, $availableFields, $hideSubmitButton) {
            $form->name(Str::snake($post->post_title));
            $form->id($post->ID);
            $form->title($post->post_title);
            $form->description($post->post_content);

            if ($hideSubmitButton) {
                $form->hideSubmitButton(true);
            }

            foreach ($rows as $rowData) {
                $rowGroup  = $rowData['childGroup'] ?? [];
                $rowFields = empty($rowGroup) ? ($rowData['fields'] ?? []) : [];

                // Just gets the row instance
                $row = $form->row(function (FieldRow $row) {
                    // No config, just gets the row instance
                }, null, true);

                if (!($row instanceof FieldRow)) {
                    continue;
                }

                if (!empty($rowFields)) {
                    foreach ($rowFields as $fieldData) {
                        $fieldType = $fieldData['type'] ?? '';
                        $fieldName = $fieldData['name'] ?? '';

                        if ($fieldType === '' || $fieldName === '') {
                            continue;
                        }

                        if (!in_array($fieldType, array_keys($availableFields))) {
                            $fieldType = Str::replace('_', '-', $fieldType);
                            
                            if (!in_array($fieldType, array_keys($availableFields))) {
                                continue;
                            }
                        }

                        $fieldProperties = $fieldData;
                        unset($fieldProperties['type']);
                        $row->field($fieldType, $fieldProperties);
                    }
                }

                else if (!empty($rowGroup)) {
                    $row->group($rowGroup);
                }
            }
        });

        return $form;
    }

    /**
     * Helper to retrieve a form's stored fields array.
     *
     * @param integer $postId
     * @param array   $config
     *
     * @return array|null
     */
    public function getStoredFields(int $postId, array $config = []): ?array {
        $form = Post::find($postId);

        if ($form === null) {
            return null;
        }

        $structure = $this->getFormStructure($postId);

        if ($structure === null) {
            return null;
        }

        $rows = $structure['rows'] ?? [];

        if ($rows === []) {
            return null;
        }

        $instance = $this->makeFormInstance($form, $rows);

        if ($instance instanceof FormComponent) {
            $fields = $instance->getFields(true);

            $listType = is_string($config['list_type'] ?? null) && in_array($config['list_type'], ['options', 'with_type', 'full'])
                ? $config['list_type']
                : 'simple';

            $appendLabel  = $config['append_label'] ?? '';
            $prependLabel = $config['prepend_label'] ?? '';

            if (in_array($listType, ['simple', 'with_type'])) {
                $arr = [];
                
                foreach ($fields as $field) {
                    if (!($field instanceof Field)) {
                        continue;
                    }

                    $name  = $field->getName();
                    $label = '';

                    if ($prependLabel !== '') {
                        $label = $prependLabel;
                    }

                    $label .= $field->getLabel();

                    if ($appendLabel !== '') {
                        $label .= ' ' . $appendLabel;
                    }

                    if ($listType === 'simple') {
                        $arr[$name] = $label;
                    } else {
                        $arr[$name] = [
                            'name'      => $name,
                            'label'     => $label,
                            'data_type' => $field->getDataType(),
                            'type'      => $field->getType()
                        ];
                    }
                }

                return $arr;
            } else {
                return $fields->toArray();
            }
        }

        return null;
    }

    /**
     * Helper to locate a particular field from a form using its ID. Used by frontend ajax requests.
     *
     * @param integer     $postId
     * @param string      $name
     * @param string      $originalName
     * @param string|null $fieldClass
     *
     * @return Field|null
     */
    public function findField(
        int $postId,
        string $name,
        string $originalName = '',
        ?string $fieldClass = null
    ): ?Field {
        $post = Post::find($postId);
        $structure = $this->getFormStructure($postId);

        if (!($post instanceof Post) || $structure === null) {
            return null;
        }

        $form = $this->makeFormInstance($post, $structure['rows'] ?? []);
        $form = apply_filters("meros_render_form_{$postId}", $form);

        if (!($form instanceof FormComponent)) {
            return null;
        }

        $fields = [];
        foreach ($form->getFields(true) as $field) {
            if (!($field instanceof Field)) {
                continue;
            }

            $fields[] = $field;

            if ($field instanceof Repeater) {
                foreach ($field->getFields(true) as $repeaterField) {
                    if ($repeaterField instanceof Field) {
                        $fields[] = $repeaterField;
                    }
                }
            }
        }

        foreach (array_unique(array_filter([$name, $originalName])) as $candidateName) {
            foreach ($fields as $field) {
                if (
                    $field->getName() === $candidateName &&
                    ($fieldClass === null || $field instanceof $fieldClass)
                ) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Retrieves a form's stored structure from the post meta table with the given id.
     *
     * @param integer $postId
     *
     * @return array|null
     */
    private function getFormStructure(int $postId): ?array {
        $model = Post::find($postId);

        if (!($model instanceof Post)) {
            return null;
        }

        $meta = $model->meta->where('meta_key', '_meros_form_structure')->first();

        if ($meta === null) {
            return null;
        }

        $unserialized = unserialize($meta->meta_value);
        if (!is_array($unserialized)) {
            return null;
        }

        return $unserialized;
    }

    /**
     * Retrieves a form's stored actions from the post meta table with the given id.
     *
     * @param integer $postId
     *
     * @return array
     */
    public function getFormActions(int $postId): array {
        $data = $this->inspect($postId);

        if (array_key_exists('form_actions', $data)) {
            $actions = $data['form_actions'];

            if (is_string($actions)) {
                return unserialize($actions);
            }

            if (is_array($actions)) {
                return $actions;
            }

            return [];
        }
        
        return [];
    }

    /**
     * Helper to retrieve available field types for the form builder.
     *
     * @return array
     */
    private function getAvailableFieldTypes(): array {
        return collect(Fields::getRegisteredFeatures())
            ->where(function ($field) {
                $class = $field['class'] ?? null;
                if (!is_string($class) || !class_exists($class)) {
                    return false;
                }

                return $class::useInFormBuilder();
            })
            ->keyBy('alias')
            ->mapWithKeys(fn ($field, $alias) => [$alias => Str::title(Str::replace('_', ' ', $alias))])
            ->all(); 
    }

    /**
     * Helper to retrieve available form actions for the form.
     *
     * @return array
     */
    private function getAvailableFormActions(): array {
        return collect(FormActions::getRegisteredFeatures())
            ->keyBy('alias')
            ->mapWithKeys(fn ($action, $alias) => [$alias => Str::title(Str::replace('_', ' ', $alias))])
            ->all();
    }
}