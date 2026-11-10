<?php

namespace MM\Meros\Contracts\Features\Content\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\Content\FormBuilder;

use MM\Meros\App\Models\Post;
use MM\Meros\App\Models\FormTemplate;
use MM\Meros\App\Models\FormResponse;

use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Contracts\Features\Data\PostMetaContainer;
use MM\Meros\Contracts\Features\Components\DynamicBlock;

use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Facades\Automation\FlowActions;
use MM\Meros\Contracts\Features\Automation\FlowControls;
use MM\Meros\Support\FormActionsRunner;

trait UsesForms {
    /**
     * The post meta key under which a form's structure is stored.
     */
    public const FORM_STRUCTURE_META_KEY = '_meros_form_structure';

    /**
     * The post meta key under which a form's actions are stored.
     */
    public const FORM_ACTIONS_META_KEY = '_meros_form_actions';

    /**
     * The post meta key under which a form's settings are stored.
     */
    public const FORM_SETTINGS_META_KEY = '_meros_form_settings';

    /**
     * The label identifying the form type associated with this post type.
     * For example, 'Registration Form' or 'Application Form'
     *
     * @var string
     */
    private string $formTypeLabel = '';

    /**
     * Indicates whether the post type has a global form.
     *
     * @var boolean
     */
    private bool $hasGlobalForm = false;

    /**
     * Indicates whether the post type has a per-post form.
     * These post types will render a FormBuilder instance for each post.
     *
     * @var boolean
     */
    private bool $hasPerPostForm = false;

    /**
     * The form template associated with the post type.
     * Can be a FormComponent instance or a stored Post representing the template.
     *
     * @var FormComponent|FormTemplate|null
     */
    private FormComponent|FormTemplate|null $formTemplate = null;

    /**
     * The FormBuilder instance associated with the post type.
     * This is used for managing/editing per-post forms.
     *
     * @var FormBuilder|null
     */
    private ?FormBuilder $formBuilder = null;

    // =========================================================================
    // Abstract Methods
    // =========================================================================

    abstract public function getHandle(string $format = 'default'): string;
    abstract public function getSingularLabel(): string;
    abstract public function fields(FieldGroup|Closure|string $fieldGroupOrClosure, Closure|array $callbackOrProps = []): PostMetaContainer;
    abstract protected function makeItem(string $class, array|Closure $callback = [], array $props = []): Makeable;

    /**
     * Sets the post type to use a global form using the specified form type label.
     * This method should be followed by a call to withTemplate() to specify the form template.
     *
     * @param string $formTypeLabel
     *
     * @return static
     */
    private function hasGlobalFormType(string $formTypeLabel): static {
        $this->formTypeLabel = $formTypeLabel;
        $this->hasGlobalForm = true;
        return $this;
    }

    /**
     * Sets the post type to use a per-post form using the specified form type label.
     * This method should be followed by a call to either withTemplate() to specify the form 
     * template or withoutTemplate() to indicate no template should be used.
     *
     * @param string $formTypeLabel
     *
     * @return static
     */
    private function hasFormType(string $formTypeLabel): static {
        $this->formTypeLabel  = $formTypeLabel;
        $this->hasPerPostForm = true;

        $this->formBuilder = new FormBuilder($this);
        return $this;
    }

    /**
     * Indicates that the post type should not use a form template.
     * This method should only be called for post types using a per-post form.
     *
     * @return static
     * @throws \RuntimeException if a form template is already set or if the post type uses a global form.
     */
    private function withoutTemplate(): static {
        if ($this->formTemplate !== null) {
            throw new \RuntimeException('Cannot use withoutTemplate when a form template is already set.');
        }

        if ($this->hasGlobalForm) {
            throw new \RuntimeException('Cannot use withoutTemplate when the post type uses a global form.');
        }

        if (!$this->hasPerPostForm) {
            throw new \RuntimeException('Cannot use withoutTemplate when the post type does not use a per-post form.');
        }

        if ($this->hasPerPostForm && $this->formBuilder instanceof FormBuilder) {
            add_action('meros_framework_booted', function () {
                $this->formBuilder->register();
            });
        }

        return $this;
    }

    /**
     * Sets the form template for the post type.
     * If $isEditable is true, the template will be stored and retrieved as a Post.
     * If $isEditable is false, the template will be used as-is.
     *
     * @param FormComponent $template
     * @param boolean       $isEditable
     *
     * @return static
     */
    private function withTemplate(FormComponent $template, bool $isEditable): static {
        $template->name($this->getHandle() . '_' . $this->formTypeLabel . '_form_template');
        $template->title($this->getSingularLabel() . ' ' . ucfirst($this->formTypeLabel) . ' Form');

        if ($isEditable) {
            $this->formTemplate = $this->getStoredFormTemplate($template);

            if (!($this->formTemplate instanceof FormTemplate)) {
                throw new \RuntimeException('Failed to retrieve or create stored form template.');
            }
            
        } else {
            $this->formTemplate = $template;
        }

        if ($this->hasPerPostForm && $this->formBuilder instanceof FormBuilder) {
            add_action('meros_framework_booted', function () {
                $this->formBuilder->register();
            });
        }

        return $this;
    }

    /**
     * Retrieves the stored form template for the post type.
     * If a template is provided and no stored template exists, it will create one.
     *
     * @param FormComponent|null $template
     *
     * @return FormTemplate|null
     */
    private function getStoredFormTemplate(?FormComponent $template = null): ?FormTemplate {
        if ($this->formTemplate instanceof FormTemplate) {
            return $this->formTemplate;
        }

        $this->formTemplate = FormTemplate::where('post_type', 'form-template')
            ->where('post_name', $this->getHandle() . '_' . $this->formTypeLabel . '_form_template')
            ->where('post_status', 'publish')
            ->first();

        if ($this->formTemplate instanceof FormTemplate) {
            return $this->formTemplate;
        }

        if ($template instanceof FormComponent) {
            $this->makeStoredFormTemplate($template);
        }

        return null;
    }

    /**
     * Creates and stores a form template as a Post if it doesn't already exist.
     *
     * @param FormComponent $template
     *
     * @return FormTemplate|null
     */
    private function makeStoredFormTemplate(FormComponent $template): ?FormTemplate {
        $templateName   = $template->getName();
        $templateTitle  = $template->getTitle() ?: Str::title(Str::replace('-', ' ', $templateName));
        $templateDescription = $template->getDescription();

        $time = current_time('mysql');
        $gmtTime = current_time('mysql', 1);

        FormTemplate::create([
            'post_name'         => $templateName,
            'post_title'        => $templateTitle,
            'post_content'      => $templateDescription,
            'post_status'       => 'publish',
            'post_date'         => $time,
            'post_date_gmt'     => $gmtTime,
            'post_modified'     => $time,
            'post_modified_gmt' => $gmtTime
        ]);

        $storedTemplate = FormTemplate::where('post_type', 'form-template')
            ->where('post_name', $this->getHandle() . '_' . $this->formTypeLabel . '_form_template')
            ->where('post_status', 'publish')
            ->first();

        if ($storedTemplate instanceof FormTemplate) {
            $structure = $template->serialize('storage');
            update_post_meta($storedTemplate->ID, self::FORM_STRUCTURE_META_KEY, $structure);

            $this->formTemplate = $storedTemplate;
            return $this->formTemplate;
        }

        return null;
    }

    /**
     * Overrides PostType's inspect method to include form metadata.
     *
     * @param integer|null $postId
     * @param boolean      $refresh
     *
     * @return array
     */
    public function inspect(?int $postId = null, bool $refresh = false): array {
        $data = parent::inspect($postId, $refresh);

        if (array_key_exists(self::FORM_STRUCTURE_META_KEY, $data) && is_string($data[self::FORM_STRUCTURE_META_KEY])) {
            $data[self::FORM_STRUCTURE_META_KEY] = unserialize($data[self::FORM_STRUCTURE_META_KEY]);
        }

        return $data;
    }

    // =========================================================================
    // Form Data
    // =========================================================================

    /**
     * Helper for FormBuilder to retrieve the meta key for the form structure.
     *
     * @return string
     */
    public function getFormStructureMetaKey(): string {
        return self::FORM_STRUCTURE_META_KEY;
    }

    /**
     * Retrieves the form structure for the given post ID, or the template if no post ID is provided.
     *
     * @param integer|null $postId
     *
     * @return array
     */
    public function getFormStructure(?int $postId = null): array {
        if ($postId === null && $this->formTemplate instanceof FormTemplate) {
            return $this->getFormStructureFromModel($this->formTemplate);
        }

        else if ($postId === null && $this->formTemplate instanceof FormComponent) {
            return $this->formTemplate->serialize('storage');
        }

        else if ($postId !== null) {
            $model = Post::find($postId);
            if ($model instanceof Post) {
                return $this->getFormStructureFromModel($model);
            }
        }
        
        return [];
    }

    /**
     * Retrieves the form structure from the given model.
     *
     * @param Post $model
     *
     * @return array
     */
    private function getFormStructureFromModel(Post $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_STRUCTURE_META_KEY);
    }

    /**
     * Retrieves the form actions from the given model.
     *
     * @param Post $model
     *
     * @return array
     */
    private function getFormActionsFromModel(Post $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_ACTIONS_META_KEY);
    }

    /**
     * Retrieves the form settings from the given model.
     *
     * @param Post $model
     *
     * @return array
     */
    private function getFormSettingsFromModel(Post $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_SETTINGS_META_KEY);
    }

    /**
     * Retrieves the specified meta value from the given model.
     *
     * @param Post $model
     * @param string    $metaKey
     *
     * @return array
     */
    private function getFormMetaFromModel(Post $model, string $metaKey): array {
        $meta = $model->meta->where('meta_key', $metaKey)->first();
        return $meta ? unserialize($meta->meta_value) : [];
    }

    // =========================================================================
    // Form Builder
    // =========================================================================

    /**
     * Internal helper to retrieve the post type's FormBuilder instance.
     *
     * @return FormBuilder
     */
    private function formBuilder(): FormBuilder {
        if (!($this->formBuilder instanceof FormBuilder)) {
            $this->formBuilder = new FormBuilder($this);
        }

        return $this->formBuilder;
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
    public function getFormInstance(Post $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
        return $this->formBuilder()->makeFormInstance($post, $rows, $availableFields, $hideSubmitButton);
    }

    /**
     * Configures the meta box used to create form actions.
     *
     * @return void
     */
    private function configureFormActionsControls(): void {
        $controls = new FlowControls($this)->getControls();
        $this->fields($controls);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Creates and configures the form block for rendering the form.
     *
     * @return void
     */
    private function makeFormBlock(): void {
        $block = $this->makeItem(DynamicBlock::class, [], [
            'name'  => $this->handle . '-form-block',
            'title' => Str::title($this->handle) . ' Form Block',
            'icon'  => 'feedback'
        ]);

        if (!($block instanceof DynamicBlock)) {
            return;
        }

        $block->attribute('title', [
            'type'    => 'string',
            'default' => Str::title($this->handle) . ' Form',
            'control' => true
        ]);

        $block->attribute('description', [
            'type'    => 'string',
            'default' => '',
            'control' => [
                'type' => 'long-text'
            ]
        ]);

        $block->attribute('show_title', [
            'type'    => 'boolean',
            'default' => true,
            'control' => true
        ]);

        $block->attribute('show_description', [
            'type'    => 'boolean',
            'default' => true,
            'control' => true
        ]);

        add_filter("meros_render_block_args_{$block->getName()}", function (array $args) {
            if (!isset($args['post_id']) || !is_int($args['post_id'])) {
                return $args;
            }

            $postID = $args['post_id'];

            if ($postID === 0) {
                return $args;
            }

            $args['form_instance'] = $this->getFormInstance(Post::find($postID));

            return $args;
        });

        $block->renderCallback(function (array $args) {
            $title = isset($args['attributes']['title']) ? $args['attributes']['title'] : ucfirst($this->formTypeLabel) . ' Form';
            $description = isset($args['attributes']['description']) ? $args['attributes']['description'] : '';
            $hideTitle = isset($args['attributes']['show_title']) && $args['attributes']['show_title'] === false;
            $hideDescription = isset($args['attributes']['show_description']) && $args['attributes']['show_description'] === false;

            if (is_admin()) {
                return '<div style="border:1px solid #dcdcde;border-radius:4px;padding:16px;">
                    <p style="margin:0 0 16px;padding:10px 12px;background:#f0f6fc;border-left:4px solid #72aee6;color:#1d2327;">
                        Forms are not fully rendered in the editor. This preview shows where the form will appear on the front end.
                    </p>
                    
                    ' . ($hideTitle === false ? '<h2>' . esc_html($title) . '</h2>' : '') . '
                    ' . ($hideDescription === false && $description !== '' ? '<p>' . esc_html($description) . '</p>' : '') . '
                    
                    <div aria-hidden="true" style="display:grid;gap:14px;">
                        <div>
                            <div style="width:22%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:36px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div>
                            <div style="width:30%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:36px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div>
                            <div style="width:18%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:72px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div style="width:112px;height:36px;border-radius:2px;background:#2271b1;"></div>
                    </div>
                </div>';
            }

            if (!isset($args['form_instance']) || !($args['form_instance'] instanceof FormComponent)) {
                return 'Error: Form instance not found.';
            }

            if ($hideTitle === false) {
                $args['form_instance']->title($title);
            }

            if ($hideDescription === false && $description !== '') {
                $args['form_instance']->description($description);
            }

            return $args['form_instance']->hideTitle($hideTitle)->hideDescription($hideDescription)->html();
        });
    }

    // =========================================================================
    // Submission Handling
    // =========================================================================

    /**
     * Handles the submission of a form, storing the response and executing associated actions.
     *
     * @param integer      $id
     * @param array|string $data
     *
     * @return array
     */
    public function handleSubmission(int $id, array|string $data): array {
        $form = Post::find($id);

        if ($form === null) {
            return [
                'message' => 'Unable to locate form: Null Form Model.',
                'type'    => 'error'
            ];
        }

        $instance = $this->getFormInstance($form);
        if (!($instance instanceof FormComponent)) {
            return [
                'message' => 'An error occurred while processing the form.',
                'type'    => 'error'
            ];
        }

        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }

        $actions = $this->getFormActions($id);
        $payload = $instance->buildSubmissionPayload($data);

        $storedResponseId = $this->storeFormResponse($id, $payload);
        if ($storedResponseId === false) {
            return [
                'message' => 'Failed to store form response.',
                'type'    => 'error'
            ];
        }

        $flowRunner = new FormActionsRunner($id, $storedResponseId, $actions, $payload);
        $result = $flowRunner->run();
        $completedActions = $flowRunner->getCompletedActions();

        $this->updateFormResponse(
            $storedResponseId, 
            $completedActions, 
            $result === false ? 'error' : 'success'
        );
    
        if ($result === false) {
            $errorType = $flowRunner->getErrorAsSuccess() ? 'success' : 'error'; 
            return [
                'message' => $errorType === 'success' ? $flowRunner->getSuccessMessage() : $flowRunner->getLastError(),
                'type'    => $errorType
            ];
        }

        $message = $flowRunner->getSuccessMessage() ?: 'Form submitted successfully.';

        return [
            'message' => $message,
            'type'    => 'success'
        ];
    }

    /**
     * Stores a form response in the database.
     *
     * @param integer $formId
     * @param array   $responseData
     *
     * @return integer|false
     */
    private function storeFormResponse(int $formId, array $responseData): int|false {
        $response = FormResponse::create([
            'form_id'  => $formId,
            'response' => $responseData,
            'status'   => 'processing'
        ]);

        if (!($response instanceof FormResponse)) {
            return false;
        }

        return $response->id;
    }

    /**
     * Updates a form response in the database.
     *
     * @param integer $responseId
     * @param array   $completedActions
     * @param string  $status
     *
     * @return boolean
     */
    private function updateFormResponse(int $responseId, array $completedActions, string $status): bool {
        $response = FormResponse::find($responseId);
        if (!($response instanceof FormResponse)) {
            return false;
        }

        $response->status = $status;
        $response->completed_actions = $completedActions;
        return $response->save();
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

    // =========================================================================
    // Helpers
    // =========================================================================

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

        $instance = $this->getFormInstance($form, $rows);

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
     * @param Post        $post
     * @param string      $name
     * @param string      $originalName
     * @param string|null $fieldClass
     *
     * @return Field|null
     */
    public function findField(
        Post $post,
        string $name,
        string $originalName = '',
        ?string $fieldClass = null
    ): ?Field {
        $postId = $post->ID ?? 0;
        $structure = $this->getFormStructure($postId);

        if (!($post instanceof Post) || $structure === null) {
            return null;
        }

        $form = $this->getFormInstance($post, $structure['rows'] ?? []);
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
}