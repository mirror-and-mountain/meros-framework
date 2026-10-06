<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Content\PostType;

use MM\Meros\Contracts\Features\Data\PostMetaContainer;
use MM\Meros\Contracts\Features\Data\PostMeta;

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

        $this->configureLookupAjaxHydration();
        $this->initFormMeta();
        $this->configureBuilderMetabox();
        $this->configurePreviewMetabox();

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

    private function configureLookupAjaxHydration(): void {
        $hydrate = function (): void {
            $action = $_REQUEST['action'] ?? '';
            $formId = isset($_REQUEST['form_id']) ? absint(wp_unslash($_REQUEST['form_id'])) : 0;

            if (
                !is_string($action) ||
                ($action !== 'meros_handle_lookup_query' && !Str::startsWith($action, 'meros_handle_lookup_query_')) ||
                $formId === 0
            ) {
                return;
            }

            $post = Post::find($formId);
            if (!($post instanceof Post) || $post->post_type !== $this->getHandle()) {
                return;
            }

            $structure = $this->getFormStructure($formId);
            $rows = $structure['rows'] ?? [];

            if (is_array($rows) && $rows !== []) {
                $this->makeFormInstance($post, $rows);
            }
        };

        add_action('init', $hydrate, 0);
    }

    private function initFormMeta(): void {
        $this->meta(function (PostMetaContainer $container) {
            $container->name('form_meta');
            $container->label('Form Metadata');
            $container->description('Contains information about the structure of the form, including fields.');
            $container->add('string', function (PostMeta $meta) {
                $meta->name('structure');
                $meta->label('Form Structure');
                $meta->description('A serialized string representing the structure of the form, including its fields and their properties.');
            });
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
        $repeaterTemplate = Fields::checkout($this->getProvider())->makeFrom('repeater', function (Repeater $repeater) {
            $repeater->name('form_row_configuration');
            $repeater->label('Row Configuration');
            $repeater->editRowText('Configure');
            $repeater->attribute('data-meros-form-component', true);
            $repeater->maxRows(3);
            $repeater->ajaxEditFormAction('meros_form_builder_edit_row');
            
            $repeater->field('select', function (Select $select) {
                $select->name('field_type');
                $select->label('Field Type');
                $select->description('Select the type of field to add to this row.');
                $select->options($this->getAvailableFieldTypes());
            });

            $repeater->field('hidden', function (Field $field) {
                $field->name('field_name');
            });

            $repeater->field('hidden', function (Field $field) {
                $field->name('field_label');
            });

            $repeater->field('hidden', function (Field $field) {
                $field->name('field_description');
            });

            $repeater->editForm(function (FormComponent $form, array $rowData): FormComponent {
                $form->title('Configure Field');
                $fieldType = $rowData['field_type'] ?? null;

                if ($fieldType === null) {
                    return $form;
                }
                                
                $form->field('text', [
                    'name'        => 'field_name',
                    'label'       => 'Field Name',
                    'description' => 'The name attribute for the field.',
                ]);

                $form->field('text', [
                    'name'     => 'field_type',
                    'label'    => 'Field Type',
                    'readonly' => true,
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

        $action = 'meros_form_add_component';

        add_action("wp_ajax_{$action}", function () use ($repeaterTemplate, $action) {
            if (!check_ajax_referer($action, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            $componentIndex = isset($_REQUEST['component_index']) ? intval($_REQUEST['component_index']) : null;
            if ($componentIndex === null) {
                wp_send_json_error(['message' => 'Missing component index.'], 400);
                exit;
            }

            $repeaterTemplate->attribute('data-meros-form-component-index', $componentIndex);
            $repeaterTemplate->name("form_components[{$componentIndex}]");

            $html = $repeaterTemplate->html();
            wp_send_json_success(['html' => $html]);
            exit;
        });

        add_action('save_post_' . $this->handle, function (int $postId) {
            if (!isset($_POST['_meros_form_builder_nonce']) || 
                !wp_verify_nonce($_POST['_meros_form_builder_nonce'], 'meros_form_structure_save')) {
                return;
            }

            if (!current_user_can('edit_post', $postId)) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            $formStructure = $_POST['form_components'] ?? [];
            $cleanRows = [];

            foreach ($formStructure as $row => $fields) {
                $cleanRow = [
                    'fields' => []
                ];

                foreach ($fields as $key => $fieldData) {
                    if ($key === -1) {
                        continue;
                    }

                    $cleanRow['fields'][] = [
                        'type'        => $fieldData['field_type'] ?? '',
                        'name'        => $fieldData['field_name'] ?? '',
                        'label'       => $fieldData['field_label'] ?? '',
                        'description' => $fieldData['field_description'] ?? '',
                    ];
                }

                $cleanRows[] = $cleanRow;
                
            }

            $formInstance = $this->makeFormInstance(Post::find($postId), $cleanRows);
            $serializedStructure = $formInstance->serialize('storage');

            update_post_meta($postId, '_meros_form_meta', $serializedStructure);
        });

        $this->metaBox('form_builder', 'Form Builder', function (\WP_Post $post) use ($repeaterTemplate): void {
            $nonce = wp_create_nonce('meros_form_add_component');
            
            $formStructure = $this->getFormStructure($post->ID);
            if (!is_array($formStructure)) {
                $formStructure = [];
            }

            $rows = $formStructure['rows'] ?? [];
            $repeaters = [];

            foreach ($rows as $index => $row) {
                $isSection = $row['childGroup'] ?? null !== null;

                $repeater = clone $repeaterTemplate;
                $repeater->attribute('data-meros-form-component-index', $index);
                $repeater->name("form_components[{$index}]");

                $repeaterValue = [];

                if ($isSection === false) {
                    $fields = $row['fields'] ?? [];

                    foreach ($fields as $field) {
                        $repeaterValue[] = [
                            'field_type'        => $field['type'] ?? '',
                            'field_name'        => $field['name'] ?? '',
                            'field_label'       => $field['label'] ?? '',
                            'field_description' => $field['description'] ?? '',
                        ];
                    }
                }

                $repeater->default($repeaterValue);
                $repeaters[] = $repeater;
            }

            echo '<div id="meros-form-builder" data-add-component-action="' . esc_attr(admin_url('admin-ajax.php?action=meros_form_add_component')) . '">';

            foreach ($repeaters as $repeater) {
                echo $repeater->html();
            }

            echo '<a href="#" id="meros-form-add-component" class="meros-admin-button button-primary" data-action="meros_form_add_component" data-nonce="' . esc_attr($nonce) . '" style="margin-top:1rem;">Add Component</a>';
            echo wp_nonce_field('meros_form_structure_save', '_meros_form_builder_nonce', true, false);
            echo '</div>';
        });
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
                $rowFields = $rowData['fields'] ?? [];
                $rowGroup  = $rowData['childGroup'] ?? [];

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