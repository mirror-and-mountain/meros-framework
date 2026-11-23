<?php

namespace MM\Meros\Contracts\Features\Content\Concerns;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use MM\Meros\App\Models\Post;
use MM\Meros\App\Models\FormTemplate;
use MM\Meros\App\Models\FormResponse;

use MM\Meros\App\Content\Form as FormCPT;
use MM\Meros\App\Content\FormTemplate as TemplateCPT;

use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Contracts\Features\Data\PostMeta;
use MM\Meros\Contracts\Features\Data\PostMetaContainer;

use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;

use MM\Meros\Support\FlowControls;
use MM\Meros\Support\FormActionsRunner;

use MM\Meros\Support\FormBuilder;
use MM\Meros\Support\FormSettingsControls;
use MM\Meros\Support\FormHostSettingsControls;
use MM\Meros\Support\FormTemplateSettingsControls;

use MM\Meros\Facades\Content\PostTypes;
use MM\Meros\Facades\Support\Context;
use MM\Meros\Facades\Support\FormDynamicValues;

trait UsesForms {
    /**
     * The post meta key under which a form's structure is stored.
     */
    public const FORM_STRUCTURE_META_KEY = '_meros_form_structure';

    /**
     * The post meta key under which a form's actions are stored.
     */
    public const FORM_ACTIONS_META_KEY = 'form_actions';

    /**
     * Indicates whether the post type has a form type associated with it.
     *
     * @var boolean
     */
    private bool $hasForm = false;

    /**
     * Indicates whether the post type should use a FormBuilder instance for each post.
     *
     * @var boolean
     */
    private bool $usesFormBuilder = false;

    /**
     * The configurable form template associated with the post type.
     *
     * @var FormTemplate|null
     */
    private FormTemplate|null $configurableFormTemplate = null;

    private ?string $configurableFormTemplateName = null;

    private bool $storedFormTemplateLookupDone = false;

    private ?FormTemplate $resolvedStoredFormTemplate = null;

    /**
     * The form component template associated with the post type.
     *
     * @var FormComponent|null
     */
    private FormComponent|null $componentFormTemplate = null;

    /**
     * If the template has an associated FormComponent template, this array stores its actions.
     *
     * @var array
     */
    private array $formComponentActions = [];

    /**
     * If the template has an associated FormComponent template, this array stores its settings.
     *
     * @var array
     */
    private array $formComponentSettings = [];

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
    abstract protected function getContainer(string $name, bool $withPrefix = false): ?PostMetaContainer;

    // =========================================================================
    // Form Handling
    // =========================================================================

    /**
     * Marks the post type as having an associated form.
     *
     * @return static
     */
    private function hasForm(): static {
        if ($this->hasForm === true) {
            throw new \LogicException('Form has already been associated with this post type.');
        }

        $this->hasForm = true;

        // Remove form capability if no template is associated with the post type.
        add_action("meros_after_post_type_configured_{$this->getHandle()}", function () {
            if ($this->hasForm && $this->componentFormTemplate === null) {
                $this->hasForm = false;
            }
        });

        return $this;
    }

    /**
     * Marks the post type as using an editable per-post form.
     *
     * @return static
     */
    private function hasEditableForm(): static {
        if ($this->usesFormBuilder === true) {
            throw new \LogicException('Per-post forms have already been associated with this post type.');
        }

        $this->hasForm         = true;
        $this->usesFormBuilder = true;
        $this->formBuilder     = new FormBuilder($this);

        // Register the form builder once the framework is booted.
        add_action('meros_framework_booted', function() {
            if ($this->hasForm && $this->usesFormBuilder) {
                $this->formBuilder()->register(
                    $this->getFormTemplateSettings(), 
                    $this->getFormTemplateActions()
                );
            }
        });
        return $this;
    }

    /**
     * Returns whether the post type has a configurable form template associated with it.
     *
     * @return boolean
     */
    public function hasConfigurableFormTemplate(): bool {
        return $this->getConfigurableFormTemplate() instanceof FormTemplate;
    }

    /**
     * Returns whether the post type has a component form template associated with it.
     *
     * @return boolean
     */
    public function hasComponentFormTemplate(): bool {
        return $this->componentFormTemplate instanceof FormComponent;
    }

    /**
     * Returns whether the post type has any form template associated with it.
     *
     * @return boolean
     */
    public function hasFormTemplate(): bool {
        return $this->hasConfigurableFormTemplate() || $this->hasComponentFormTemplate();
    }

    /**
     * Returns the form template associated with the post type, if any.
     * 
     * @param string $type The type of form template to retrieve ('default', 'configurable', 'component').
     *
     * @return FormTemplate|FormComponent|null
     */
    public function getFormTemplate(string $type = 'default'): FormTemplate|FormComponent|null {
        if ($type === 'default') {
            return $this->getConfigurableFormTemplate() ?? $this->getComponentFormTemplate();
        }

        if ($type === 'configurable') {
            return $this->getConfigurableFormTemplate();
        }

        if ($type === 'component') {
            return $this->getComponentFormTemplate();
        }

        return null;
    }

    /**
     * Returns the configurable form template associated with the post type, if any.
     *
     * @return FormTemplate|null
     */
    public function getConfigurableFormTemplate(): FormTemplate|null {
        return $this->resolveStoredFormTemplate();
    }

    /**
     * Returns the component form template associated with the post type, if any.
     *
     * @return FormComponent|null
     */
    public function getComponentFormTemplate(): FormComponent|null {
        return $this->componentFormTemplate;
    }

    // =========================================================================
    // Template Handling
    // =========================================================================

    /**
     * Associates a configurable form template with the post type.
     *
     * A configurable template allows stores a FormTemplate instance associated with the post type for future use.
     *
     * @param string                     $templateName
     * @param FormComponent|Closure|null $componentTemplate
     * @param array                      $actions
     * @param array                      $settings
     *
     * @return static
     */
    private function withConfigurableTemplate(
        string $templateName, 
        FormComponent|Closure|null $componentTemplate = null,
        array $actions = [],
        array $settings = [],
    ): static {
        return $this->__withTemplate($templateName, $componentTemplate, true, $actions, $settings);
    }
    /**
     * Associates a standard FormComponent template with the post type.
     *
     * @param string                     $templateName
     * @param FormComponent|Closure|null $componentTemplate
     * @param array                      $actions
     * @param array                      $settings
     *
     * @return static
     */
    private function withTemplate(
        string $templateName, 
        FormComponent|Closure|null $componentTemplate,
        array $actions = [],
        array $settings = [],
    ): static {
        return $this->__withTemplate($templateName, $componentTemplate, false, $actions, $settings);
    }

    /**
     * Internal helper for associating a form template with the post type.
     *
     * @param string                     $templateName
     * @param FormComponent|Closure|null $componentTemplate
     * @param bool                       $isConfigurable
     * @param array                      $actions
     * @param array                      $settings
     *
     * @return static
     */
    private function __withTemplate(
        string $templateName, 
        FormComponent|Closure|null $componentTemplate = null, 
        bool $isConfigurable = false,
        array $actions = [],
        array $settings = [],
    ): static {
        if ($this instanceof FormTemplate) {
            throw new \LogicException('Cannot associate a form template with a FormTemplate instance.');
        }

        if ($componentTemplate instanceof Closure) {
            $componentTemplate = $this->makeItem(FormComponent::class, $componentTemplate);
        }

        if ($isConfigurable) {
            $this->configurableFormTemplateName = Str::slug($templateName);
        }

        // Create missing configurable templates only when editing in wp admin.
        if ($isConfigurable && (Context::isEditingPostType($this->getHandle()) || Context::isEditingPostType('form-template'))) {
            $this->configurableFormTemplate = $this->__getStoredFormTemplate(
                $templateName, 
                $componentTemplate,
                $actions,
                $settings
            );
            if ($componentTemplate instanceof FormComponent) {
                $this->componentFormTemplate = $componentTemplate;
                $this->formComponentActions  = $actions;
                $this->formComponentSettings = $settings;
            }

        } else {
            $this->componentFormTemplate = $componentTemplate;
            $this->formComponentActions  = $actions;
            $this->formComponentSettings = $settings;
        }

        return $this;
    }

    /**
     * For internal use only.
     * 
     * Retrieves the stored form template for the post type.
     * If a template is provided and no stored template exists, it will create one.
     *
     * @param string             $name              The name of the form template.
     * @param FormComponent|null $componentTemplate The form component template, if any.
     * @param array              $actions           The actions to be associated with the form template.
     * @param array              $settings          The settings to be associated with the form template.
     *
     * @return FormTemplate
     */
    private function __getStoredFormTemplate(
        string $name, 
        ?FormComponent $componentTemplate = null,
        array $actions = [],
        array $settings = []
    ): FormTemplate {
        if ($this->configurableFormTemplate instanceof FormTemplate) {
            return $this->configurableFormTemplate;
        }

        $formattedName = Str::slug($name);

        $template = FormTemplate::where('post_type', 'form-template')
            ->where('post_name', $formattedName)
            ->where('post_status', 'publish')
            ->first();

        if ($template instanceof FormTemplate) {
            return $template;
        }

        return $this->__makeStoredFormTemplate(
            $formattedName, 
            $componentTemplate, 
            $actions, 
            $settings
        );
    }

    /**
     * For internal use only.
     * 
     * Creates and stores a form template as a Post if it doesn't already exist.
     *
     * @param string             $formattedName     The formatted (slug) name of the form template.
     * @param FormComponent|null $componentTemplate The form component template to use for creating the form template.
     * @param array              $actions           The actions to be associated with the form template.
     * @param array              $settings          The settings to be associated with the form template.
     *
     * @return FormTemplate
     */
    private function __makeStoredFormTemplate(
        string $formattedName, 
        ?FormComponent $componentTemplate = null,
        array $actions = [],
        array $settings = []
    ): FormTemplate {
        if (!($componentTemplate instanceof FormComponent)) {
            $templateTitle = Str::title(Str::replace('-', ' ', $formattedName));
            $templateDescription = '';
        } else {
            $templateTitle  = $componentTemplate->getTitle() ?: Str::title(Str::replace('-', ' ', $formattedName));
            $templateDescription = $componentTemplate->getDescription();

            foreach ($componentTemplate->getFields() as $field) {
                $field->templateField();
            }
        }

        $time = current_time('mysql');
        $gmtTime = current_time('mysql', 1);

        FormTemplate::create([
            'post_name'         => $formattedName,
            'post_title'        => $templateTitle,
            'post_content'      => $templateDescription,
            'post_status'       => 'publish',
            'post_date'         => $time,
            'post_date_gmt'     => $gmtTime,
            'post_modified'     => $time,
            'post_modified_gmt' => $gmtTime
        ]);

        $storedTemplate = FormTemplate::where('post_type', 'form-template')
            ->where('post_name', $formattedName)
            ->where('post_status', 'publish')
            ->first();

        if ($storedTemplate instanceof FormTemplate && $componentTemplate instanceof FormComponent) {
            $structure = $componentTemplate->serialize('storage');
            update_post_meta($storedTemplate->ID, self::FORM_STRUCTURE_META_KEY, $structure);
        }

        if ($storedTemplate instanceof FormTemplate && !empty($actions)) {
            update_post_meta($storedTemplate->ID, self::FORM_ACTIONS_META_KEY, $actions);
        }

        if ($storedTemplate instanceof FormTemplate && !empty($settings)) {
            update_post_meta($storedTemplate->ID, $this->__getFormSettingsMetaKey('template', true), $settings);
        }

        return $storedTemplate;
    }

    // =========================================================================
    // Actions Handling
    // =========================================================================

    /**
     * Initialises the form actions editor.
     *
     * @return static
     */
    private function withActionsEditor(): static {
        add_action('meros_framework_booted', function () {
            $this->__configureFormActionsControls();
        });

        return $this;
    }

    /**
     * For internal use only.
     * Configures the meta box used to create form actions.
     *
     * @return void
     */
    private function __configureFormActionsControls(): void {
        $controls = new FlowControls($this)->getControls()->getId();
        $this->fields($controls);
    }

    // =========================================================================
    // Form Settings
    // =========================================================================

    /**
     * Initialises the form settings editor.
     *
     * @return static
     */
    private function withFormSettings(): static {
        add_action('meros_framework_booted', function () {
            $this->__configureFormSettingsControls();
        });

        return $this;
    }

    /**
     * For internal use only.
     * Configures the meta box used to display form settings.
     *
     * @return void
     */
    private function __configureFormSettingsControls(): void {
        if ($this instanceof FormCPT) {
            $controls = new FormSettingsControls()->getControls()->getId();
            $this->fields($controls);
            return;
        }

        if ($this instanceof TemplateCPT) {
            $controls = new FormTemplateSettingsControls()->getControls()->getId();
            $this->fields($controls);
            return;
        }

        $templateSettings = $this->hasFormTemplate() ? array_merge($this->getFormTemplateSettings(), [
            'template_name' => $this->getFormTemplateName(),
            'template_title' => $this->getFormTemplateTitle(),
            'template_description' => $this->getFormTemplateDescription()
        ]) : [];

        $controls = new FormHostSettingsControls($templateSettings)->getControls()->getId();
        $this->fields($controls);

        $template = $this->getConfigurableFormTemplate();
        if ($template instanceof FormTemplate) {
            $container = $this->getContainer('form_host_settings');
            if ($container instanceof PostMetaContainer) {
                $formTemplateName = $container->getItems(true)->firstWhere(function (PostMeta $item) {
                    return $item->getName() === 'form_template';
                });

                if ($formTemplateName instanceof PostMeta) {
                    $formTemplateName->default($template->post_name);
                }

                $formTemplateId = $container->getItems(true)->firstWhere(function (PostMeta $item) {
                    return $item->getName() === 'form_template_id';
                });

                if ($formTemplateId instanceof PostMeta) {
                    $formTemplateId->default($template->ID);
                }
            }
        }
            
    }

    // =========================================================================
    // Form Data
    // =========================================================================

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

        if (array_key_exists(self::FORM_ACTIONS_META_KEY, $data)) {
            $data[self::FORM_ACTIONS_META_KEY] = $this->__parseFormActions($data[self::FORM_ACTIONS_META_KEY]);
        }

        if (array_key_exists('_meros_form_actions', $data)) {
            $data[self::FORM_ACTIONS_META_KEY] = $this->__parseFormActions($data['_meros_form_actions']);
        }

        return $data;
    }

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
    public function getFormStructure(?int $postId = null, bool $refresh = false): array {
        if ($postId === null) {
            return $this->getFormTemplateStructure($refresh);
        }

        else if ($postId !== null) {
            $data = $this->inspect($postId, $refresh);
            if (array_key_exists(self::FORM_STRUCTURE_META_KEY, $data)) {
                return $data[self::FORM_STRUCTURE_META_KEY];
            }

            return $this->getFormTemplateStructure($refresh);
        }

        return [];
    }

    /**
     * Helper to retrieve the form structure from a template, if one exists.
     *
     * @param boolean $refresh
     *
     * @return array
     */
    public function getFormTemplateStructure(bool $refresh = false): array {
        $template = $this->resolveStoredFormTemplate();
        if ($template instanceof FormTemplate) {
            $data = $this->inspect($template->ID, $refresh);
            if (array_key_exists(self::FORM_STRUCTURE_META_KEY, $data)) {
                return $data[self::FORM_STRUCTURE_META_KEY];
            }
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->componentFormTemplate->serialize('storage');
        }

        return [];
    }

    /**
     * Retrieves the form actions for the given post ID, or the template if no post ID is provided.
     *
     * @param integer|null $postId
     *
     * @return array
     */
    public function getFormActions(?int $postId = null, bool $refresh = false): array {
        if ($postId === null && $this->getConfigurableFormTemplate() instanceof FormTemplate) {
            return $this->getFormTemplateActions($refresh);
        }

        else if ($postId === null && $this->componentFormTemplate instanceof FormComponent) {
            return $this->formComponentActions;
        }

        else if ($postId !== null) {
            $data = $this->inspect($postId, $refresh);
            if (array_key_exists(self::FORM_ACTIONS_META_KEY, $data)) {
                return $data[self::FORM_ACTIONS_META_KEY];
            }

            return $this->getFormTemplateActions($refresh);
        }

        return [];
    }

    /**
     * Helper to retrieve the form actions from a template, if one exists.
     *
     * @param boolean $refresh
     *
     * @return array
     */
    public function getFormTemplateActions(bool $refresh = false): array {
        $template = $this->getConfigurableFormTemplate();
        if ($template instanceof FormTemplate) {
            $data = $this->inspect($template->ID, $refresh);
            return array_key_exists(self::FORM_ACTIONS_META_KEY, $data) 
                ? $data[self::FORM_ACTIONS_META_KEY]
                : [];
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->formComponentActions;
        }

        return [];
    }


    /**
     * Parses the form actions into a structured array.
     *
     * @param array|string $actions
     *
     * @return array
     */
    private function __parseFormActions(array|string $actions): array {
        if (is_string($actions)) {
            $actions = unserialize($actions);
        }

        if (!array_key_exists('form_actions', $actions)) {
            return $actions;
        }

        $actions = $actions['form_actions'];

        foreach ($actions as $index => $action) {
            foreach ($action as $property => $value) {
                if ($property === '__form_data') {
                    $value = is_string($value) ? json_decode($value, true) : $value;
                    $actions[$index] = array_merge($action, $value);
                    unset($actions[$index]['__form_data']);
                    break;
                }
            }
        }
        return $actions;
    }

    /**
     * Retrieves the form settings for the given post ID, or the template if no post ID is provided.
     *
     * @param integer|null $postId
     * @param boolean      $refresh
     *
     * @return array
     */
    public function getFormSettings(?int $postId = null, bool $refresh = false): array {
        if ($postId === null && $this->getConfigurableFormTemplate() instanceof FormTemplate) {
            return $this->getFormTemplateSettings($refresh);
        }

        else if ($postId === null && $this->componentFormTemplate instanceof FormComponent) {
            return $this->formComponentSettings;
        }

        else if ($postId !== null) {
            $container = null;

            if ($this instanceof TemplateCPT) {
                $container = $this->getContainer('form_template_settings');
            }

            else if ($this instanceof FormCPT) {
                $container = $this->getContainer('form_settings');
            }
            
            else if ($this instanceof FormHost) {
                $container = $this->getContainer('form_host_settings');
            }

            if ($container === null) {
                return [];
            }

            return $container->value($postId, $refresh);
        }

        return [];
    }

    /**
     * Helper to retrieve the form settings from a template, if one exists.
     *
     * @param boolean $refresh
     *
     * @return array
     */
    public function getFormTemplateSettings(bool $refresh = false): array {
        $template = $this->getConfigurableFormTemplate();

        if ($template instanceof FormTemplate) {
            $templateCPT = PostTypes::get('form-template');

            if ($templateCPT instanceof TemplateCPT) {
                return $templateCPT->getFormSettings($template->ID, $refresh);
            }

            return [];
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->formComponentSettings;
        }

        return [];
    }

    /**
     * Retrieves the name of the form template or component associated with the current post type.
     *
     * @return string
     */
    public function getFormTemplateName(): string {
        $template = $this->getConfigurableFormTemplate();

        if ($template instanceof FormTemplate) {
            return $template->post_name;
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->componentFormTemplate->getName();
        }

        return '';
    }

    /**
     * Retrieves the title of the form template or component associated with the current post type.
     *
     * @return string
     */
    public function getFormTemplateTitle(): string {
        $template = $this->getConfigurableFormTemplate();

        if ($template instanceof FormTemplate) {
            return $template->post_title;
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->componentFormTemplate->getTitle();
        }

        return '';
    }

    /**
     * Retrieves the description of the form template or component associated with the current post type.
     *
     * @return string
     */
    public function getFormTemplateDescription(): string {
        $template = $this->getConfigurableFormTemplate();

        if ($template instanceof FormTemplate) {
            return $template->post_content;
        }

        if ($this->componentFormTemplate instanceof FormComponent) {
            return $this->componentFormTemplate->getDescription();
        }

        return '';
    }

    /**
     * Parses form settings into an array.
     *
     * @param string|array $settings
     *
     * @return array
     */
    private function __parseFormSettings(string|array $settings): array {
        if (is_string($settings)) {
            return unserialize($settings);
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * For internal use only.
     * Returns the meta key used to store the form settings for the current post type.
     * 
     * @param string $type The type of form settings key to retrieve (default, actions, settings).
     *
     * @return string
     */
    private function __getFormSettingsMetaKey(string $type = 'default', bool $withPrefix = false): string {
        if ($type === 'template' || ($type === 'default' && $this instanceof TemplateCPT)) {
            return $withPrefix ? '_meros_form_template_settings' : 'form_template_settings';
        }

        if ($type === 'form' || ($type === 'default' && $this instanceof FormCPT)) {
            return $withPrefix ? '_meros_form_settings' : 'form_settings';
        }

        if ($type === 'host' || ($type === 'default' && $this instanceof FormHost)) {
            return $withPrefix ? '_meros_form_host_settings' : 'form_host_settings';
        }

        throw new \InvalidArgumentException("Invalid form settings type: {$type}");
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
     * @param Post|int $post
     * @param array    $rows
     * @param array    $availableFields
     * @param boolean  $hideSubmitButton
     *
     * @return FormComponent
     */
    public function getFormInstance(Post|int $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
        if (is_int($post)) {
            $post = Post::find($post);
        }

        if (!($post instanceof Post)) {
            throw new \InvalidArgumentException('Invalid post provided.');
        }

        return $this->formBuilder()->makeFormInstance($post, $rows, $availableFields, $hideSubmitButton);
    }

    // =========================================================================
    // Submission Handling
    // =========================================================================

    /**
     * Logs an error message related to form submission.
     *
     * @param string $message
     *
     * @return void
     */
    private function logSubmissionError(string $message): void {
        Log::channel('meros-flows')->error($message);
    }

    /**
     * Handles the submission of a form, storing the response and executing associated actions.
     *
     * @param integer      $id
     * @param array|string $data
     * @param array        $hiddenFields Field names reported hidden by the browser.
     *
     * @return array
     */
    public function handleSubmission(int $id, array|string $data, array $hiddenFields = []): array {
        $form = Post::find($id);

        if ($form === null) {
            return [
                'message' => 'Unable to locate form: Null Form Model.',
                'type'    => 'error'
            ];
        }

        $instance = $this->getFormInstance($form);
        if (!($instance instanceof FormComponent)) {
            $this->logSubmissionError('Failed to create form instance. Form ID: ' . $id);
            return [
                'message' => 'An error occurred while processing the form.',
                'type'    => 'error'
            ];
        }

        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }

        $actions = $this->getFormActions($id);
        $payload = $instance->buildSubmissionPayload($data, $hiddenFields);

        $storedResponseId = $this->storeFormResponse($id, $payload);
        if ($storedResponseId === false) {
            $this->logSubmissionError('Failed to store form response. Form ID: ' . $id);
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
    
        if ($result === false && !$flowRunner->getErrorAsSuccess()) {
            $this->logSubmissionError('Form submission error: ' . $flowRunner->getLastError());
            return [
                'message' => $flowRunner->getLastError(),
                'type'    => 'error'
            ];
        }

        $actionMessage = $flowRunner->getSuccessMessage();
        $message = $actionMessage
            ? wp_kses_post($actionMessage)
            : $this->parseFormSuccessMessage($instance->getSuccessMessage(), $payload['data']);

        return [
            'message' => $message,
            'type'    => 'success'
        ];
    }

    /**
     * Parses the form success message and replaces dynamic value placeholders with their resolved values.
     *
     * @param string $message
     * @param array  $fields
     *
     * @return string
     */
    private function parseFormSuccessMessage(string $message, array $fields): string {
        $submitted = [];
        foreach ($fields as $name => $entry) {
            if (isset($entry['field_type']) && $entry['field_type'] !== 'password') {
                $submitted[$name] = [
                    'type' => \MM\Meros\Support\FormDynamicValues::typeOf($entry['user_value']),
                    'value' => $entry['user_value'],
                ];
            }
        }

        $addSubmittedValues = static fn (array $values): array => ['submitted_fields' => $submitted] + $values;
        add_filter('meros_dynamic_form_values', $addSubmittedValues, PHP_INT_MAX);
        try {
            return FormDynamicValues::parseHtml($message);
        } finally {
            remove_filter('meros_dynamic_form_values', $addSubmittedValues, PHP_INT_MAX);
        }
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

            $listType = is_string($config['list_type'] ?? null) && in_array($config['list_type'], ['options', 'with_type', 'full', 'template'])
                ? $config['list_type']
                : 'simple';

            $appendLabel  = $config['append_label'] ?? '';
            $prependLabel = $config['prepend_label'] ?? '';

            if (in_array($listType, ['simple', 'with_type', 'template'])) {
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
                    
                    } else if ($listType === 'template') {
                        $arr[$name] = $this->formatTemplateField($field, $label);
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
     * Retrieves the template fields for the form, either from a 
     * configurable form template or a component form template.
     *
     * @return array|null
     */
    private function resolveStoredFormTemplate(): ?FormTemplate {
        $template = $this->configurableFormTemplate;
        if ($template === null && $this->configurableFormTemplateName !== null) {
            if (!$this->storedFormTemplateLookupDone) {
                $this->resolvedStoredFormTemplate = FormTemplate::where('post_type', 'form-template')
                    ->where('post_name', $this->configurableFormTemplateName)
                    ->where('post_status', 'publish')
                    ->first();
                $this->storedFormTemplateLookupDone = true;
            }
            $template = $this->resolvedStoredFormTemplate;
        }
        return $template;
    }

    /**
     * Retrieves the template fields for the form, either from a 
     * configurable form template or a component form template.
     *
     * @return array|null
     */
    public function getTemplateFields(): ?array {
        $template = $this->resolveStoredFormTemplate();
        if ($template instanceof FormTemplate) {
            return $this->getStoredFields(
                $template->ID,
                [
                    'list_type' => 'template'
                ]
            );
        }

        if ($this->hasComponentFormTemplate()) {
            $fields = [];
            foreach ($this->componentFormTemplate->getFields() as $field) {
                $fields[$field->getName()] = $this->formatTemplateField($field, $field->getLabel());
            }
            return $fields;
        }

        return null;
    }

    /**
     * Formats a template field into an array structure suitable for use in forms.
     *
     * @param Field  $field
     * @param string $label
     *
     * @return array
     */
    private function formatTemplateField(Field $field, string $label): array {
        return [
            'name'        => $field->getName(),
            'label'       => $label,
            'description' => $field->getDescription(),
            'default'     => $field->getDefaultValue(),
            'locked'      => $field->isLocked(),
            ...$field->serialize('storage')
        ];
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