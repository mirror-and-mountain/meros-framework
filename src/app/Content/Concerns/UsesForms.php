<?php

namespace MM\Meros\App\Content\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\Content\Form;
use MM\Meros\App\Content\FormBuilder;

use MM\Meros\App\Models\Form as FormModel;
use MM\Meros\App\Models\FormResponse;

use MM\Meros\Contracts\Features\Data\PostMetaContainer;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
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
     * @var FormComponent|FormModel|null
     */
    private FormComponent|FormModel|null $formTemplate = null;

    /**
     * The FormBuilder instance associated with the post type.
     * This is used for managing/editing per-post forms.
     *
     * @var FormBuilder|null
     */
    private ?FormBuilder $formBuilder = null;

    abstract public function getHandle(string $format = 'default'): string;
    abstract public function fields(FieldGroup|Closure|string $fieldGroupOrClosure, Closure|array $callbackOrProps = []): PostMetaContainer;

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
     * @return void
     * @throws \RuntimeException if a form template is already set or if the post type uses a global form.
     */
    private function withoutTemplate(): void {
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
            $this->formBuilder->register();
        }
    }

    /**
     * Sets the form template for the post type.
     * If $isEditable is true, the template will be stored and retrieved as a Post.
     * If $isEditable is false, the template will be used as-is.
     *
     * @param FormComponent $template
     * @param boolean       $isEditable
     *
     * @return void
     */
    private function withTemplate(FormComponent $template, bool $isEditable): void {
        if ($isEditable) {
            $this->formTemplate = $this->getStoredFormTemplate($template);
            if (!($this->formTemplate instanceof FormModel)) {
                throw new \RuntimeException('Failed to retrieve or create stored form template.');
            }
        } else {
            $this->formTemplate = $template;
        }
        
        if ($this->hasPerPostForm && $this->formBuilder instanceof FormBuilder) {
            $this->formBuilder->register($this->formTemplate);
        }
    }

    /**
     * Retrieves the stored form template for the post type.
     * If a template is provided and no stored template exists, it will create one.
     *
     * @param FormComponent|null $template
     *
     * @return FormModel|null
     */
    private function getStoredFormTemplate(?FormComponent $template = null): ?FormModel {
        if ($this->formTemplate instanceof FormModel) {
            return $this->formTemplate;
        }

        $this->formTemplate = FormModel::where('post_type', 'form')
            ->where('post_name', $this->formTypeLabel)
            ->first();

        if ($this->formTemplate instanceof FormModel) {
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
     * @return FormModel|null
     */
    private function makeStoredFormTemplate(FormComponent $template): ?FormModel {
        $templateName   = $template->getName();
        $templateTitle  = $template->getTitle() ?: Str::title(Str::replace('-', ' ', $templateName));
        $templateDescription = $template->getDescription();

        $time = current_time('mysql');
        $gmtTime = current_time('mysql', 1);

        $storedTemplate = FormModel::create([
            'post_name'         => $templateName,
            'post_title'        => $templateTitle,
            'post_content'      => $templateDescription,
            'post_status'       => 'publish',
            'post_date'         => $time,
            'post_date_gmt'     => $gmtTime,
            'post_modified'     => $time,
            'post_modified_gmt' => $gmtTime
        ]);

        if ($storedTemplate instanceof FormModel) {
            $structure = $template->serialize('storage');
            update_post_meta($storedTemplate->ID, FormBuilder::getStructureMetaKey(), $structure);

            $this->formTemplate = $storedTemplate;
            return $this->formTemplate;
        }

        return null;
    }

    // =========================================================================
    // Form Data
    // =========================================================================

    public function getFormStructure(?int $postId = null): array {
        if ($postId === null && $this->formTemplate instanceof FormModel) {
            return $this->getFormStructureFromModel($this->formTemplate);
        }
        
    }

    private function getFormStructureFromModel(FormModel $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_STRUCTURE_META_KEY);
    }

    private function getFormActionsFromModel(FormModel $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_ACTIONS_META_KEY);
    }

    private function getFormSettingsFromModel(FormModel $model): array {
        return $this->getFormMetaFromModel($model, self::FORM_SETTINGS_META_KEY);
    }

    private function getFormMetaFromModel(FormModel $model, string $metaKey): array {
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
     * @param FormModel    $post
     * @param array   $rows
     * @param array   $availableFields
     * @param boolean $hideSubmitButton
     *
     * @return FormComponent
     */
    public function makeFormInstance(FormModel $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent {
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

}