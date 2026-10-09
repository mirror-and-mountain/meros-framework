<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;
use Livewire\Livewire;

use MM\Meros\Contracts\Features\Content\PostType;

use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Components\Fields;

use MM\Meros\App\Models\Post;

class Form extends PostType {
    protected function configure(): void {
        $this->public(true);
        $this->menuIcon('dashicons-feedback');

        $this->configureBuilderMetabox();
        // $this->configurePreviewMetabox();

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

            return $form->html();
        });
    }

    private function configurePreviewMetabox(): void {
        $this->metaBox('form_preview', 'Form Preview', function (\WP_Post $post): void {
            $unavailable = '<p>This form is not available for preview.</p>';
            if ($this->renderCallback === null) {
                echo $unavailable;
                return;
            }

            $model = Post::find($post->ID);
            echo call_user_func($this->renderCallback, '', $post->ID, $model);
        });
    }

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

                $repeater->editForm(function (FormComponent $form) {
                    $form->field('text', [
                        'name'     => 'field_type',
                        'label'    => 'Field Type',
                        'readonly' => true,
                    ]);

                    $form->field('text', [
                        'name'  => 'field_name', 
                        'label' => 'Field Name'
                    ]);

                    $form->field('text', [
                        'name'        => 'field_label',
                        'label'       => 'Field Label',
                        'description' => 'The label for the field.',
                    ]);

                    $form->field('text', [
                        'name'        => 'field_description',
                        'label'       => 'Field Description',
                        'description' => 'A description for the field.',
                    ]);

                    return $form;
                });
            });
    }

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
            $cleanRows = [];
            $groupIds = [];
            $fieldNames = [];
            $groupNames = [];

            foreach ($formStructure as $rowData) {
                if (!is_array($rowData)) {
                    continue;
                }

                $cleanRow = [];
                $fields = $this->getSubmittedBuilderValue($rowData, 'fields', []);
                $group = $this->getSubmittedBuilderValue($rowData, 'childGroup');

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

            update_post_meta($postId, '_meros_form_meta', $serializedStructure);
        });
    }

    private function getSubmittedBuilderValue(array $data, string $key, mixed $default = null): mixed {
        foreach ([$key, "'{$key}'"] as $candidate) {
            if (array_key_exists($candidate, $data)) {
                return $data[$candidate];
            }
        }

        return $default;
    }

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

                $fields[] = [
                    'type'        => $data['field_type'],
                    'name'        => $name,
                    'label'       => is_string($data['field_label'] ?? null) ? $data['field_label'] : '',
                    'description' => is_string($data['field_description'] ?? null) ? $data['field_description'] : '',
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

                        $definitions[] = [
                            'type'        => $type,
                            'name'        => $field['name'],
                            'label'       => $field['label'],
                            'description' => $field['description'],
                        ];
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

    private function makeUniqueGeneratedName(string $prefix, array &$usedNames): string {
        do {
            $name = $prefix . '_' . Str::substr(Str::uuid(), 0, 8);
        } while (isset($usedNames[$name]));

        $usedNames[$name] = true;

        return $name;
    }

    private function makeFormInstance(Post $post, array $rows, array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
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
                        $fieldType        = $fieldData['type'] ?? '';
                        $fieldName        = $fieldData['name'] ?? '';
                        $fieldLabel       = $fieldData['label'] ?? '';
                        $fieldDescription = $fieldData['description'] ?? '';

                        if ($fieldType === '' || $fieldName === '') {
                            continue;
                        }

                        if (!in_array($fieldType, array_keys($availableFields))) {
                            $fieldType = Str::replace('_', '-', $fieldType);
                            
                            if (!in_array($fieldType, array_keys($availableFields))) {
                                continue;
                            }
                        }

                        $row->field($fieldType, function (Field $field) use ($fieldName, $fieldLabel, $fieldDescription) {
                            $field->name($fieldName);
                            $field->label($fieldLabel);
                            $field->description($fieldDescription);
                        });
                    }
                }

                else if (!empty($rowGroup)) {
                    $row->group($rowGroup);
                }
            }
        });

        return $form;
    }

    private function getFormStructure(int $postId): ?array {
        $model = Post::find($postId);

        if (!($model instanceof Post)) {
            return null;
        }

        $meta = $model->meta->where('meta_key', '_meros_form_meta')->first();

        if ($meta === null) {
            return null;
        }

        $unserialized = unserialize($meta->meta_value);
        if (!is_array($unserialized)) {
            return null;
        }

        return $unserialized;
    }

    private function getAvailableFieldTypes(): array {
        return collect(Fields::getRegisteredFeatures())
            ->keyBy('alias')
            ->mapWithKeys(fn ($field, $alias) => [$alias => ucfirst($alias)])
            ->all(); 
    }
}