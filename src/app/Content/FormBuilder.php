<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;
use Livewire\Livewire;

use MM\Meros\App\Models\Post;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Components\Fields;

/**
 * Provides the form builder for a post type. The builder renders the builder meta box
 * on the post's edit screen, persists the submitted form structure as post meta and
 * turns a stored structure back into a Form component instance.
 */
class FormBuilder {

    /**
     * The post meta key under which a form's structure is stored.
     */
    public const STRUCTURE_META_KEY = '_meros_form_structure';

    /**
     * The post type that owns the form builder.
     *
     * @var PostType
     */
    private PostType $postType;

    public function __construct(PostType $postType) {
        $this->postType = $postType;
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Registers the builder meta box and the save action for the owning post type.
     *
     * @return void
     */
    public function register(): void {
        $componentTemplate = $this->makeComponentTemplate();
        $this->addSaveAction();

        $this->postType->metaBox('form_builder', 'Form Builder', function (\WP_Post $post) use ($componentTemplate): void {
            $formData = $this->getStructure($post->ID);

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
    // Config Persistance
    // =========================================================================

    /**
     * Configures the save action for the post type to save the form's structural data as post meta.
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

            update_post_meta($postId, self::STRUCTURE_META_KEY, $serializedStructure);
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
    // Structure
    // =========================================================================

    /**
     * Retrieves a form's stored structure from the post meta table with the given id.
     *
     * @param integer $postId
     *
     * @return array|null
     */
    public function getStructure(int $postId): ?array {
        $model = Post::find($postId);

        if (!($model instanceof Post)) {
            return null;
        }

        $meta = $model->meta->where('meta_key', self::STRUCTURE_META_KEY)->first();

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
     *
     * @return FormComponent
     */
    public function makeFormInstance(Post $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
        $rows = empty($rows) ? $this->getStructure($post->ID)['rows'] ?? [] : $rows;
        $availableFields = empty($availableFields) ? $this->getAvailableFieldTypes() : $availableFields;

        $form = Forms::checkout($this->postType->getProvider())->make(function (FormComponent $form) use ($post, $rows, $availableFields, $hideSubmitButton) {
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
}
