<?php

namespace MM\Meros\App\Content;

use Illuminate\Support\Str;

use MM\Meros\App\Models\Post;
use MM\Meros\App\Models\FormResponse;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\App\Components\Fields\Repeater;

use MM\Meros\Facades\Automation\FlowActions;
use MM\Meros\Support\FormActionsRunner;

class Form extends PostType {

    private ?FormBuilder $builder = null;

    // =========================================================================
    // Configuration
    // =========================================================================

    protected function configure(): void {
        $this->public();
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');

        add_action('meros_framework_booted', function () {
            $this->builder()->register();
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

        if (array_key_exists(FormBuilder::STRUCTURE_META_KEY, $data) && is_string($data[FormBuilder::STRUCTURE_META_KEY])) {
            $data[FormBuilder::STRUCTURE_META_KEY] = unserialize($data[FormBuilder::STRUCTURE_META_KEY]);
        }

        return $data;
    }

    // =========================================================================
    // Form Builder
    // =========================================================================

    /**
     * Retrieves the form builder instance for this post type.
     *
     * @return FormBuilder
     */
    public function builder(): FormBuilder {
        return $this->builder ??= new FormBuilder($this);
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
        return $this->builder()->makeFormInstance($post, $rows, $availableFields, $hideSubmitButton);
    }

    /**
     * Retrieves a form's stored structure from the post meta table with the given id.
     *
     * @param integer $postId
     *
     * @return array|null
     */
    private function getFormStructure(int $postId): ?array {
        return $this->builder()->getStructure($postId);
    }

    /**
     * Configures the meta box used to create form actions.
     *
     * @return void
     */
    private function configureFormActionsFields(): void {
        $group = new FormActionsGroup($this)->getGroup();
        $this->fields($group);
    }

    // =========================================================================
    // Submission Handling
    // =========================================================================

    /**
     * Handles the submission of a form, storing the response and executing associated actions.
     *
     * @param integer      $formId
     * @param array|string $data
     *
     * @return array
     */
    public function handleSubmission(int $formId, array|string $data): array {
        $form = Post::find($formId);

        if ($form === null) {
            return [
                'message' => 'Unable to locate form: Null Form Model.',
                'type'    => 'error'
            ];
        }

        $instance = $this->makeFormInstance($form);
        if (!($instance instanceof FormComponent)) {
            return [
                'message' => 'An error occurred while processing the form.',
                'type'    => 'error'
            ];
        }

        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }

        $actions = $this->getFormActions($formId);
        $payload = $instance->buildSubmissionPayload($data);

        $storedResponseId = $this->storeFormResponse($formId, $payload);
        if ($storedResponseId === false) {
            return [
                'message' => 'Failed to store form response.',
                'type'    => 'error'
            ];
        }

        $flowRunner = new FormActionsRunner($formId, $storedResponseId, $actions, $payload);
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
     * Helper to retrieve available form actions for the form.
     *
     * @return array
     */
    private function getAvailableFormActions(): array {
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