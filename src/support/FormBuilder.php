<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

use MM\Meros\App\Models\Post;

use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

use MM\Meros\App\Content\FormTemplate;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Lookup;

use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Components\Fields;
use MM\Meros\Facades\Support\Ajax;

/**
 * Provides the form builder for a post type. The builder renders the builder meta box
 * on the post's edit screen, persists the submitted form structure as post meta and
 * turns a stored structure back into a Form component instance.
 */
class FormBuilder {
    /**
     * The post type that owns the form builder. Must implement the FormHost interface.
     *
     * @var FormHost
     */
    private FormHost $postType;

    /**
     * Whether the current form is a FormTemplate instance.
     *
     * @var boolean
     */
    private bool $isFormTemplate = false;

    /**
     * Settings provided by the form host's template if one exists.
     *
     * @var array
     */
    private array $templateSettings = [];

    /**
     * Actions provided by the form host's template if one exists.
     *
     * @var array
     */
    private array $templateActions = [];

    public function __construct(FormHost $postType) {
        $this->postType = $postType;
        $this->isFormTemplate = $postType instanceof FormTemplate;
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Registers the builder meta box and the save action for the owning post type.
     * 
     * @param array $templateSettings The settings provided by the form host's template.
     * @param array $templateActions  The actions provided by the form host's template.
     *
     * @return void
     */
    public function register(array $templateSettings = [], array $templateActions = []): void {
        $this->templateSettings = $templateSettings;
        $this->templateActions  = $templateActions;

        $componentTemplate = $this->makeComponentTemplate();
        $repeaterFieldTemplate = $this->makeRepeaterFieldTemplate();

        $this->addSaveAction();
        $action = $this->registerRepeaterDefaultsAction();

        $this->postType->metaBox('form_builder', 'Form Builder', function (\WP_Post $post) use ($componentTemplate, $repeaterFieldTemplate, $action): void {
            $formData = [];
            $formData = $this->postType->getFormStructure($post->ID);
            echo Livewire::mount('meros::form-builder', [
                'formData'              => $formData,
                'componentTemplate'     => $componentTemplate,
                'repeaterFieldTemplate' => $repeaterFieldTemplate,
                'postId'                => $post->ID,
                'repeaterDefaultsAction' => $action,
                'repeaterDefaultsNonce' => Ajax::getActionNonce($action),
                'isFormTemplate'        => $this->isFormTemplate,
                'templateSettings'      => $this->templateSettings,
                'templateLockSettings'  => $this->isFormTemplate ? $this->postType->getFormSettings($post->ID) : $this->templateSettings,
                'templateActions'       => $this->templateActions,
                'templateFields'        => $this->postType->getTemplateFields() ?? [],
                'templateRows'          => !$this->isFormTemplate && $this->postType->hasFormTemplate()
                    ? ($this->postType->getFormStructure()['rows'] ?? [])
                    : [],
            ]);
        });
    }

    /**
     * Creates a field or fieldgroup component editor template for the form builder.
     * These take the form of a repeater field.
     *
     * @return Repeater
     */
    private function makeComponentTemplate(): Repeater {
        return Fields::checkout($this->postType->getProvider())
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
                    'options' => $this->getAvailableColumnTypes(),
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

                $repeater->field('checkbox', [
                    'name'    => '__is_form_template',
                    'label'   => 'Form Template',
                    'default' => $this->isFormTemplate,
                    'hide'    => true
                ]);

                $repeater->field('checkbox', [
                    'name'    => '__is_template_field',
                    'label'   => 'Template Field',
                    'default' => $this->isFormTemplate,
                    'hide'    => true
                ]);

                foreach (FormTemplateComponentLocks::SETTINGS as $flag => $setting) {
                    $repeater->field('checkbox', [
                        'name'    => $flag,
                        'label'   => $flag,
                        'default' => false,
                        'hide'    => true,
                    ]);
                }

                $repeater->editForm(function (FormComponent $form, array $rowData): FormComponent {
                    return $this->configureFieldEditForm($form, $rowData);
                }, 'Edit field', 'Edit Field', 'field_type');
            });
    }

    /**
     * Creates a repeater field template for the form builder.
     *
     * @return Repeater
     */
    private function makeRepeaterFieldTemplate(): Repeater {
        return Fields::checkout($this->postType->getProvider())
            ->makeFrom('repeater', function (Repeater $repeater) {
                $repeater->name('repeater_column_component');
                $repeater->label('Repeater Columns');
                $repeater->addRowText('Add field');

                $repeater->field('select', [
                    'name'    => 'field_type',
                    'label'   => 'Field Type',
                    'options' => $this->getAvailableColumnTypes(),
                    'default' => 'text',
                ]);

                $repeater->field('text', ['name' => 'field_label', 'label' => 'Field Label']);
                $repeater->field('hidden', ['name' => 'field_name', 'default' => 'new_field_0']);
                $repeater->field('hidden', ['name' => 'field_description']);

                $repeater->editForm(function (FormComponent $form, array $rowData): FormComponent {
                    return $this->configureFieldEditForm($form, $rowData, false);
                }, 'Edit column', 'Edit Repeater Field', 'field_type');
            });
    }

    /**
     * Returns the available column types for the repeater field.
     *
     * @return array
     */
    private function getAvailableColumnTypes(): array {
        return array_diff_key($this->getAvailableFieldTypes(), ['repeater' => true]);
    }

    /**
     * Registers the AJAX action for handling repeater defaults.
     *
     * @return string
     */
    private function registerRepeaterDefaultsAction(): string {
        $action = 'meros_builder_repeater_defaults_' . $this->postType->getHandle();
        Ajax::addAction($action, function (array $args): void {
            $postId = filter_var($args['post_id'] ?? null, FILTER_VALIDATE_INT);
            $post = $postId ? Post::find($postId) : null;
            if (!$post || $post->post_type !== $this->postType->getHandle() || !current_user_can('edit_post', $postId)) {
                wp_send_json_error(['message' => 'You cannot edit this form.'], 403);
                return;
            }

            try {
                $raw = $args['columns'] ?? '';
                if (!is_string($raw)) {
                    throw new \InvalidArgumentException('Invalid repeater columns.');
                }
                $columns = json_decode(wp_unslash($raw), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($columns) || !array_is_list($columns)) {
                    throw new \InvalidArgumentException('Invalid repeater columns.');
                }

                $names = [];
                $submitted = [];
                foreach ($columns as $column) {
                    if (!is_array($column) || !is_string($column['field_type'] ?? null) ||
                        !array_key_exists($column['field_type'], $this->getAvailableColumnTypes()) ||
                        !is_string($column['field_name'] ?? null) || $column['field_name'] === '' ||
                        isset($names[$column['field_name']])) {
                        throw new \InvalidArgumentException('Each repeater column must have a valid type and unique name.');
                    }
                    $names[$column['field_name']] = true;
                    $settings = $column['form_data'] ?? [];
                    if (!is_array($settings)) {
                        throw new \InvalidArgumentException('Invalid repeater column settings.');
                    }
                    $fieldClass = Fields::getRegisteredFeatureClass($column['field_type']);
                    if (!is_string($fieldClass) || !is_subclass_of($fieldClass, \MM\Meros\Contracts\Features\Components\Field::class)) {
                        throw new \InvalidArgumentException('Invalid repeater column type.');
                    }
                    $cleanColumn = [
                        'field_type' => $column['field_type'],
                        'field_name' => $column['field_name'],
                        'field_label' => $column['field_label'] ?? '',
                        'field_description' => $column['field_description'] ?? '',
                        'form_data' => $settings,
                    ];
                    $allowedSettings = array_fill_keys([
                        'options', 'allowsMultiple', 'searchable', 'defaultValue', '__builder_default_value',
                        'placeholder', 'required', 'disabled', 'readonly', 'postType', 'postStatus',
                    ], true);
                    $controls = $fieldClass::getBuilderControls(false, $cleanColumn);
                    if ($controls instanceof FieldGroup) {
                        foreach ($controls->getFields() as $control) {
                            $allowedSettings[$control->getName()] = true;
                        }
                    }
                    $cleanColumn['__form_data'] = json_encode(array_intersect_key($settings, $allowedSettings), JSON_THROW_ON_ERROR);
                    unset($cleanColumn['form_data']);
                    $submitted[] = $cleanColumn;
                }
                $fieldNames = [];
                $fields = $this->parseSubmittedFieldComponents($submitted, $fieldNames);
                if ($fields === []) {
                    throw new \InvalidArgumentException('Add at least one repeater column first.');
                }

                if (($args['mode'] ?? 'render') === 'lookup') {
                    $name = $args['field_name'] ?? '';
                    $search = $args['search'] ?? '';
                    if (!is_string($name) || !is_string($search) || mb_strlen($search) > 200) {
                        throw new \InvalidArgumentException('Invalid lookup request.');
                    }
                    $definition = current(array_filter($fields, fn ($field) => $field['name'] === $name));
                    if (!$definition) {
                        throw new \InvalidArgumentException('The lookup column was not found.');
                    }
                    $lookup = Fields::checkout($this->postType->getProvider())->makeFrom($definition['type'], $definition);
                    if (!$lookup instanceof Lookup) {
                        throw new \InvalidArgumentException('The selected column is not a lookup.');
                    }
                    wp_send_json_success(['results' => $lookup->resolve($search)]);
                    return;
                }

                $rawDefaults = $args['defaults'] ?? '[]';
                if (!is_string($rawDefaults)) {
                    throw new \InvalidArgumentException('Invalid repeater defaults.');
                }
                $defaults = $this->normaliseRepeaterDefaults(
                    json_decode(wp_unslash($rawDefaults), true, 512, JSON_THROW_ON_ERROR),
                    $fields
                );
                $repeater = Fields::checkout($this->postType->getProvider())->makeFrom('repeater', [
                    'name' => 'builder_repeater_default',
                    'label' => 'Default rows',
                    'fields' => $fields,
                    'defaultValue' => $defaults,
                ]);
                wp_send_json_success(['html' => $repeater->html(['resolveDynamicValues' => false], true)]);
            } catch (\JsonException | \InvalidArgumentException $exception) {
                wp_send_json_error(['message' => $exception->getMessage()], 400);
            }
        });
        return $action;
    }

    /**
     * Configures the edit form for a repeater field row, applying template lock settings if necessary.
     *
     * @param FormComponent $form
     * @param array         $rowData
     * @param boolean       $withTemplateLock
     *
     * @return FormComponent
     */
    private function configureFieldEditForm(FormComponent $form, array $rowData, bool $withTemplateLock = true): FormComponent {
        $form->resolveDynamicValues(false);

        $fieldType = $rowData['field_type'] ?? '';
        if (empty($fieldType)) {
            return $form;
        }

        $isTemplate = $withTemplateLock && ($rowData['__is_form_template'] ?? false);
        $locks = $withTemplateLock ? FormTemplateComponentLocks::flags($rowData, null, [], true) : [];
        $isLockedByTemplate = $locks['__properties_locked_by_template'] ?? false;

        if ($withTemplateLock) {
            $form->row(function ($row) use ($isTemplate, $locks) {
                foreach ([
                    '__properties_locked_by_template' => 'Lock Field Properties',
                    '__move_locked_by_template' => 'Prevent Moving',
                    '__remove_locked_by_template' => 'Prevent Removal',
                ] as $flag => $label) {
                    $row->field('checkbox', [
                        'name'     => $flag,
                        'label'    => $label,
                        'toggle'   => true,
                        'hide'     => !$isTemplate && !$locks[$flag],
                        'default'  => $locks[$flag],
                        'disabled' => !$isTemplate,
                    ]);
                }
            });
        }

        $fieldClass = Fields::getRegisteredFeatureClass($fieldType);
        if (!is_string($fieldClass) || !class_exists($fieldClass)) {
            return $form;
        }

        if ($withTemplateLock) {
            $form->row(function ($row) use ($fieldClass, $isLockedByTemplate, $isTemplate) {
                $row->field('hidden', [
                    'name' => '__builder_condition_options',
                    'default' => method_exists($fieldClass, 'setOptionsWhen') ? '1' : '0',
                ]);
                $row->field('hidden', [
                    'name' => '__builder_conditions_locked',
                    'default' => $isLockedByTemplate && !$isTemplate ? '1' : '0',
                ]);
            });
        }

        $controls = $fieldClass::getBuilderControls($isLockedByTemplate && !$isTemplate, $rowData);
        if ($controls instanceof FieldGroup) {
            $form->section($controls);
        }
        return $form;
    }

    // =========================================================================
    // Config Persistance
    // =========================================================================

    /**
     * Configures the save action for the post type to save its form's structural data as post meta.
     *
     * @return void
     */
    private function addSaveAction(): void {
        add_action('save_post_' . $this->postType->getHandle(), function (int $postId) {
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
            $skippedRepeaters = [];

            foreach ($formStructure as $rowData) {
                if (!is_array($rowData)) {
                    continue;
                }

                $cleanRow = [];
                $fields   = $this->getSubmittedBuilderValue($rowData, 'fields', []);
                $group    = $this->getSubmittedBuilderValue($rowData, 'childGroup');

                if (is_array($group) && $group !== []) {
                    $cleanRow['childGroup'] = $this->parseSubmittedFieldGroup($group, $groupIds, $fieldNames, $groupNames, $skippedRepeaters);
                } elseif (is_array($fields)) {
                    $cleanRow['fields'] = $this->parseSubmittedFieldComponents($fields, $fieldNames, $skippedRepeaters);
                }

                if ($cleanRow !== []) {
                    $cleanRows[] = $cleanRow;
                }
            }

            $formInstance = $this->makeFormInstance(Post::find($postId), $cleanRows, useStoredRows: false);
            $serializedStructure = $formInstance->serialize('storage');
            $removedConditions = [];
            $serializedStructure['rows'] = $this->removeInvalidConditionRules(
                $serializedStructure['rows'] ?? [],
                $this->getAvailableFieldTypes(),
                $removedConditions
            );
            if ($removedConditions !== []) {
                Log::warning('Removed invalid form builder condition rules.', [
                    'post_id' => $postId,
                    'rules' => $removedConditions,
                ]);
            }
            if ($skippedRepeaters !== []) {
                Log::warning('Skipped form builder repeaters without columns.', [
                    'post_id' => $postId,
                    'fields' => $skippedRepeaters,
                ]);
            }

            update_post_meta($postId, $this->postType->getFormStructureMetaKey(), $serializedStructure);
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
    private function parseSubmittedFieldComponents(array $components, array &$fieldNames, array &$skippedRepeaters = []): array {
        $fields = [];
        $collectFields = function (array $data, int|string|null $rowIndex = null) use (&$collectFields, &$fields, &$fieldNames, &$skippedRepeaters): void {
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
                foreach (array_merge(['__is_template_field', '__is_form_template'], array_keys(FormTemplateComponentLocks::SETTINGS)) as $key) {
                    $raw = $data[$key] ?? ($key === '__properties_locked_by_template' ? ($data['__is_locked_by_template'] ?? false) : false);
                    $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        throw new \InvalidArgumentException("Invalid checkbox value for '{$key}'.");
                    }
                    $data[$key] = $value;
                }

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

                $conditionData = $data['__builder_conditions'] ?? $decodedSettings['__builder_conditions'] ?? null;
                if ($conditionData !== null) {
                    $decodedSettings['conditions'] = $this->decodeBuilderConditions($conditionData);
                }
                unset($decodedSettings['__builder_conditions'], $decodedSettings['__builder_condition_options'], $decodedSettings['__builder_conditions_locked']);

                $default = $decodedSettings['defaultValue'] ?? null;
                $fieldClass = Fields::getRegisteredFeatureClass($data['field_type']);
                if (is_string($default) && str_starts_with($default, '{{') && is_string($fieldClass) &&
                    is_subclass_of($fieldClass, \MM\Meros\Contracts\Features\Components\Fields\Input::class)) {
                    if (preg_match('/^\{\{\s*([^{}]+?)\s*\}\}$/D', $default, $matches) !== 1 || trim($matches[1]) === '') {
                        throw new \InvalidArgumentException("Invalid dynamic default for field '{$name}'.");
                    }
                    $definition = \MM\Meros\Facades\Support\FormDynamicValues::definition($default);
                    $field = Fields::checkout($this->postType->getProvider())->makeFrom($data['field_type']);
                    if ($definition === null || !$field->isCompatibleWithDataType($definition['type'])) {
                        throw new \InvalidArgumentException("Dynamic default for field '{$name}' must resolve to a compatible registered type.");
                    }
                }

                if (in_array($data['field_type'], ['select', 'radio', 'checkboxes'], true) &&
                    array_key_exists('__builder_default_value', $decodedSettings)) {
                    if (!is_string($decodedSettings['__builder_default_value'])) {
                        throw new \InvalidArgumentException('Choice default value must be JSON.');
                    }
                    try {
                        $default = json_decode($decodedSettings['__builder_default_value'], true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $exception) {
                        throw new \InvalidArgumentException('Invalid choice default value JSON.', 0, $exception);
                    }
                    if ($default !== null && !is_string($default) &&
                        (!is_array($default) || !array_is_list($default) ||
                            count(array_filter($default, 'is_string')) !== count($default))) {
                        throw new \InvalidArgumentException('Invalid choice default value.');
                    }
                    $options = [];
                    foreach ($decodedSettings['options'] ?? [] as $key => $option) {
                        $value = is_array($option) ? ($option['value'] ?? null) : $key;
                        $label = is_array($option) ? ($option['label'] ?? null) : $option;
                        if ((is_string($value) || is_int($value)) && (string) $value !== '' && is_string($label) && $label !== '') {
                            $options[(string) $value] = true;
                        }
                    }
                    $values = is_array($default) ? $default : ($default === null ? [] : [$default]);
                    $values = array_values(array_filter($values, fn ($value) => isset($options[$value])));
                    $multiple = $data['field_type'] === 'checkboxes' ||
                        ($data['field_type'] === 'select' && filter_var($decodedSettings['allowsMultiple'] ?? false, FILTER_VALIDATE_BOOLEAN));
                    $decodedSettings['defaultValue'] = $multiple ? $values : ($values[0] ?? null);
                    unset($decodedSettings['__builder_default_value']);
                }

                if ($data['field_type'] === 'repeater') {
                    $columnNames = [];
                    $columns = $this->getSubmittedBuilderValue($data, 'repeater_fields', []);
                    if (!is_array($columns)) {
                        throw new \InvalidArgumentException('Repeater field columns must be an array.');
                    }
                    $decodedSettings['fields'] = $this->parseSubmittedFieldComponents($columns, $columnNames, $skippedRepeaters);
                    if ($decodedSettings['fields'] === []) {
                        $skippedRepeaters[] = $name;
                        return;
                    }
                    foreach ($decodedSettings['fields'] as $column) {
                        if ($column['type'] === 'repeater') {
                            throw new \InvalidArgumentException('Nested repeater fields are not supported.');
                        }
                    }
                    $rawDefaults = $data['__builder_repeater_defaults'] ?? null;
                    if ($rawDefaults !== null) {
                        if (!is_string($rawDefaults)) {
                            throw new \InvalidArgumentException('Repeater defaults must be JSON.');
                        }
                        try {
                            $defaults = json_decode($rawDefaults, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\JsonException $exception) {
                            throw new \InvalidArgumentException('Invalid repeater defaults JSON.', 0, $exception);
                        }
                        $decodedSettings['defaultValue'] = $this->normaliseRepeaterDefaults($defaults, $decodedSettings['fields']);
                    } elseif (array_key_exists('defaultValue', $decodedSettings)) {
                        $decodedSettings['defaultValue'] = $this->normaliseRepeaterDefaults($decodedSettings['defaultValue'], $decodedSettings['fields']);
                    }
                }

                // Clear any empty rich text descriptions that are represented as '<p><br></p>'
                if (is_string($data['field_description'] ?? null) && $data['field_description'] === '<p><br></p>') {
                    $data['field_description'] = '';
                }

                $fields[] = [
                    'type'        => $data['field_type'],
                    'name'        => $name,
                    'label'       => is_string($data['field_label'] ?? null) ? $data['field_label'] : '',
                    'description' => is_string($data['field_description'] ?? null) ? wp_kses_post($data['field_description']) : '',
                    ...$decodedSettings,
                    '__is_template_field'     => $data['__is_template_field'],
                    '__properties_locked_by_template' => $data['__properties_locked_by_template'],
                    '__move_locked_by_template' => $data['__move_locked_by_template'],
                    '__remove_locked_by_template' => $data['__remove_locked_by_template'],
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
     * Normalises the default values for a repeater field based on its defined fields.
     *
     * @param mixed $defaults
     * @param array $fields
     *
     * @return array
     */
    private function normaliseRepeaterDefaults(mixed $defaults, array $fields): array {
        if ($defaults === null) {
            return [];
        }
        if (!is_array($defaults) || !array_is_list($defaults)) {
            throw new \InvalidArgumentException('Repeater defaults must be a list of rows.');
        }
        $names = array_fill_keys(array_column($fields, 'name'), true);
        $rows = [];
        foreach ($defaults as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Invalid repeater default row.');
            }
            $values = [];
            foreach ($row as $name => $value) {
                if (!isset($names[$name])) {
                    continue;
                }
                if (!is_scalar($value) && $value !== null &&
                    (!is_array($value) || !array_is_list($value) ||
                        count(array_filter($value, 'is_scalar')) !== count($value))) {
                    throw new \InvalidArgumentException('Invalid repeater default field value.');
                }
                $values[$name] = $value;
            }
            $rows[] = $values;
        }
        return $rows;
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
    private function parseSubmittedFieldGroup(array $group, array &$groupIds, array &$fieldNames, array &$groupNames, array &$skippedRepeaters = []): array {
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
                    foreach ($this->parseSubmittedFieldComponents($fields, $fieldNames, $skippedRepeaters) as $field) {
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

        $name        = $this->getSubmittedBuilderValue($group, 'name');
        $title       = $this->getSubmittedBuilderValue($group, 'title');
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
        $name          = is_string($name) && $name !== '' && $name !== 'group'
            ? $name
            : '';

        if ($name === '' || preg_match('/^new_group(?:_\d+)?$/', $name) === 1) {
            $name = $this->makeUniqueGeneratedName('group', $groupNames);
        } else {
            $groupNames[$name] = true;
        }

        // Clear any empty rich text descriptions that are represented as '<p><br></p>'
        if (is_string($description) && $description === '<p><br></p>') {
            $description = '';
        }

        $definition = [
            'name'        => $name,
            'title'       => is_string($title) ? $title : 'Group',
            'description' => is_string($description) ? wp_kses_post($description) : '',
            'rows'        => $groupRows,
            'id'          => $id,
        ];

        foreach (FormTemplateComponentLocks::SETTINGS as $flag => $setting) {
            $value = filter_var($this->getSubmittedBuilderValue($group, $flag, false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) {
                throw new \InvalidArgumentException("Invalid checkbox value for '{$flag}'.");
            }
            $definition[$flag] = $value;
        }

        $conditions = $this->getSubmittedBuilderValue($group, '__builder_conditions');
        if ($conditions !== null) {
            $definition['conditions'] = $this->decodeBuilderConditions($conditions);
        }

        return $definition;
    }

    /**
     * Decodes and validates the builder conditions JSON for a group component.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function decodeBuilderConditions(mixed $value): array {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Builder conditions must be JSON.');
        }
        try {
            $conditions = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid builder conditions JSON.', 0, $exception);
        }
        if (!is_array($conditions) || ($conditions !== [] && array_is_list($conditions))) {
            throw new \InvalidArgumentException('Builder conditions must be an object.');
        }
        foreach (['show', 'hide', 'set_options'] as $type) {
            if (!array_key_exists($type, $conditions)) {
                continue;
            }
            $config = $conditions[$type];
            if (!is_array($config) || !is_array($config['rules'] ?? null) ||
                !in_array($config['logic'] ?? 'AND', ['AND', 'OR'], true)) {
                throw new \InvalidArgumentException("Invalid '{$type}' condition configuration.");
            }
            foreach ($config['rules'] as $rule) {
                if (!is_array($rule) || !is_string($rule['field'] ?? null) || $rule['field'] === '' ||
                    !is_string($rule['operator'] ?? null) || $rule['operator'] === '' ||
                    (!array_key_exists('source_value', $rule) && !array_key_exists('value', $rule)) ||
                    ($type === 'set_options' && !is_array($rule['dest_value'] ?? null))) {
                    throw new \InvalidArgumentException("Invalid '{$type}' condition rule.");
                }
            }
        }
        return $conditions;
    }

    /**
     * Removes invalid condition rules from the form builder rows based on available field types.
     *
     * @param array $rows
     * @param array $availableTypes
     * @param array $removed
     *
     * @return array
     */
    private function removeInvalidConditionRules(array $rows, array $availableTypes, array &$removed): array {
        $conditionSources = FormConditionSources::definitions();
        $precedingFields = [];
        $filterConditions = function (array &$component, ?string $fieldName = null) use ($conditionSources, &$precedingFields, &$removed): void {
            if (!is_array($component['conditions'] ?? null)) {
                return;
            }

            foreach ($component['conditions'] as $type => &$config) {
                if (!is_array($config) || !is_array($config['rules'] ?? null)) {
                    continue;
                }

                $rules = [];
                foreach ($config['rules'] as $rule) {
                    $source = is_array($rule) ? ($rule['field'] ?? null) : null;
                    $operator = is_array($rule) ? ($rule['operator'] ?? null) : null;
                    $contextSource = is_string($source) ? ($conditionSources[$source] ?? null) : null;
                    if ($contextSource !== null) {
                        $value = $rule['source_value'] ?? $rule['value'] ?? null;
                        if (in_array($type, ['show', 'hide'], true) &&
                            in_array($operator, ['=', '!='], true) &&
                            FormConditionSources::allowsValue($contextSource, $value)) {
                            $rules[] = $rule;
                            continue;
                        }
                    }
                    $sourceType = is_string($source) ? ($precedingFields[$source] ?? null) : null;
                    if ($contextSource !== null || $source === FormConditionSources::SIGNED_IN ||
                        $sourceType === null || $source === $fieldName ||
                        ($sourceType === 'repeater' && !in_array($operator, ['EMPTY', 'NOT EMPTY'], true))) {
                        $removed[] = [
                            'target' => $component['name'] ?? '',
                            'condition' => $type,
                            'source' => is_string($source) ? $source : '',
                        ];
                        continue;
                    }
                    $rules[] = $rule;
                }

                if ($rules === []) {
                    unset($component['conditions'][$type]);
                } else {
                    $config['rules'] = $rules;
                }
            }
            unset($config);
            if (array_key_exists('conditionsString', $component)) {
                $component['conditionsString'] = $component['conditions'] === []
                    ? 'false'
                    : json_encode($component['conditions'], JSON_THROW_ON_ERROR);
            }
        };

        $filterRows = function (array &$rows) use (&$filterRows, &$precedingFields, $availableTypes, $filterConditions): void {
            foreach ($rows as &$row) {
                if (!is_array($row)) {
                    continue;
                }
                if (is_array($row['childGroup'] ?? null) && $row['childGroup'] !== []) {
                    $filterConditions($row['childGroup']);
                    if (is_array($row['childGroup']['rows'] ?? null)) {
                        $filterRows($row['childGroup']['rows']);
                    }
                    continue;
                }
                if (!is_array($row['fields'] ?? null)) {
                    continue;
                }
                foreach ($row['fields'] as &$field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $name = $field['name'] ?? null;
                    $type = $field['type'] ?? null;
                    if (!is_string($name) || $name === '' || !is_string($type)) {
                        continue;
                    }
                    $normalizedType = str_replace('_', '-', $type);
                    if (!array_key_exists($type, $availableTypes) && !array_key_exists($normalizedType, $availableTypes)) {
                        continue;
                    }
                    $filterConditions($field, $name);
                    $precedingFields[$name] = $normalizedType;
                }
                unset($field);
            }
            unset($row);
        };

        $filterRows($rows);
        return $rows;
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
    // Helpers
    // =========================================================================

    /**
     * Helper to retrieve available field types for the form builder.
     *
     * @return array
     */
    public function getAvailableFieldTypes(): array {
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
     * Creates a Form instance using the form's structure.
     *
     * @param Post    $post
     * @param array   $rows
     * @param array   $availableFields
     * @param boolean $hideSubmitButton
     * @param boolean $useStoredRows Whether empty rows should fall back to the stored structure.
     *
     * @return FormComponent
     */
    public function makeFormInstance(Post $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false, bool $useStoredRows = true): FormComponent {
        $rows = empty($rows) && $useStoredRows ? $this->postType->getFormStructure($post->ID)['rows'] ?? [] : $rows;
        $availableFields = empty($availableFields) ? $this->getAvailableFieldTypes() : $availableFields;

        // A template's own reconstruction must not recursively load its template fields.
        if (!$this->isFormTemplate && $post->post_type !== 'form-template') {
            $templateGroups = FormTemplateComponentLocks::groups($this->postType->getFormStructure()['rows'] ?? []);
            $rows = $this->applyTemplateFields($rows, $this->postType->getTemplateFields() ?? [], $availableFields, $templateGroups);
        }
        if ($useStoredRows) {
            $removedConditions = [];
            $rows = $this->removeInvalidConditionRules($rows, $availableFields, $removedConditions);
        }

        $form = Forms::checkout($this->postType->getProvider())->make(function (FormComponent $form) use ($post, $rows, $availableFields, $hideSubmitButton) {
            $form->name(Str::snake($post->post_title));
            $form->id($post->ID);
            $form->title($post->post_title);
            $form->description($post->post_content);
            $settings = $this->postType->getFormSettings($post->ID);
            $templateSettings = $this->postType->hasFormTemplate() ? $this->postType->getFormSettings() : [];
            foreach ([
                'display_as_pages' => 'displayAsPages',
                'show_success_on_new_page' => 'showSuccessOnNewPage',
                'show_submit_another_response' => 'showSubmitAnotherResponse',
            ] as $key => $method) {
                $locked = filter_var($templateSettings['lock_' . $key] ?? false, FILTER_VALIDATE_BOOLEAN);
                $value = $locked
                    ? ($templateSettings[$key] ?? false)
                    : ($settings[$key] ?? $templateSettings[$key] ?? false);
                $form->{$method}(filter_var($value, FILTER_VALIDATE_BOOLEAN));
            }

            foreach ([
                'submit_button_text' => ['submitText', 'allow_edit_submit_button_text'],
                'success_message' => ['successMessage', 'allow_edit_success_message'],
            ] as $key => [$method, $permission]) {
                $locked = array_key_exists($permission, $templateSettings) &&
                    !filter_var($templateSettings[$permission], FILTER_VALIDATE_BOOLEAN);
                $value = $locked
                    ? ($templateSettings[$key] ?? null)
                    : ($settings[$key] ?? $templateSettings[$key] ?? null);
                if ($value !== null) {
                    $form->{$method}($value);
                }
            }

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
     * Applies template fields to the given rows, ensuring locked fields are preserved and context is maintained.
     *
     * @param array $rows
     * @param array $templateFields
     * @param array $availableFields
     *
     * @return array
     */
    private function applyTemplateFields(array $rows, array $templateFields, array $availableFields, array $templateGroups = []): array {
        foreach ($rows as &$row) {
            foreach ($row['fields'] ?? [] as $index => $field) {
                $templateField = $templateFields[$field['name'] ?? ''] ?? null;
                if (!is_array($templateField) || !FormTemplateComponentLocks::flags($field, $templateField, $this->templateSettings, false)['__properties_locked_by_template']) {
                    continue;
                }

                $type = $templateField['type'] ?? '';
                $normalizedType = Str::replace('_', '-', $type);
                if (!array_key_exists($type, $availableFields) && array_key_exists($normalizedType, $availableFields)) {
                    $templateField['type'] = $normalizedType;
                }

                foreach (['formId', 'groupId', 'rowIndex', 'rowPosition', 'repeaterId'] as $contextKey) {
                    unset($templateField[$contextKey]);
                    if (array_key_exists($contextKey, $field)) {
                        $templateField[$contextKey] = $field[$contextKey];
                    }
                }

                $row['fields'][$index] = $templateField;
            }

            if (!empty($row['childGroup'])) {
                $group = &$row['childGroup'];
                $templateGroup = $templateGroups[$group['name'] ?? ''] ?? null;
                if (is_array($templateGroup) && FormTemplateComponentLocks::flags($group, $templateGroup, $this->templateSettings, false)['__properties_locked_by_template']) {
                    foreach (['title', 'description', 'conditions', 'conditionsString'] as $key) {
                        if (array_key_exists($key, $templateGroup)) {
                            $group[$key] = $templateGroup[$key];
                        } else {
                            unset($group[$key]);
                        }
                    }
                }
                $group['rows'] = $this->applyTemplateFields($group['rows'] ?? [], $templateFields, $availableFields, $templateGroups);
                unset($group);
            }
        }
        unset($row);

        return $rows;
    }
}
