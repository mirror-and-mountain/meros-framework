<?php 

namespace MM\Meros\App\Toolbox\Forms\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Models\Form;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\FormActions;

trait ManagesFormSchema {
    /**
     * The form being built or edited.
     *
     * @var Form|null
     */
    public ?Form $form = null;

    /**
     * The ID of the form being built or edited.
     *
     * @var string|int|null
     */
    public string|int|null $formID = null;

    /**
     * The title of the form being built or edited.
     *
     * @var string
     */
    public string $formTitle = '';

    /**
     * The description of the form being built or edited.
     *
     * @var string
     */
    public string $formDescription = '';

    /**
     * The slug of the form being built or edited.
     *
     * @var string
     */
    public string $formSlug = '';

    /**
     * The status of the form being built or edited.
     *
     * @var string
     */
    public string $formStatus = '';

    /**
     * The form actions associated with the current form.
     *
     * @var array
     */
    public array $actionPayloads = [];

    /**
     * The available field types for the form.
     *
     * @var array
     */
    public array $fieldTypes = [];

    /**
     * The available field categories for the form.
     *
     * @var array
     */
    public array $fieldCategories = [];

    /**
     * The available field groups for the form.
     *
     * @var array
     */
    public array $fieldGroups = [];

    /**
     * The form schema as an array.
     *
     * @var array
     */
    public array $schema = [];

    /**
     * The form's row payloads
     *
     * @var array
     */
    public array $rowPayloads = [];

    /**
     * Retrieves available field types from the Fields register 
     * and sets available field categories.
     *
     * @return void
     */
    private function initialiseFields(): void {
        foreach (Fields::getRegistered() as $handle => $fieldType) {
            if (!$fieldType::$showInFormBuilder) {
                continue;
            }

            $this->fieldTypes[ $handle ] = $fieldType;
            $category                    = $fieldType::getCategory();

            $this->fieldCategories[ $category ][ $handle ] = [
                'handle' => $handle,
                'class'  => $fieldType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
                'icon'   => $fieldType::getIcon(),
            ];
        }
    }

    /**
     * Retrieves available field groups from the FieldGroups register.
     *
     * @return void
     */
    private function initialiseFieldGroups(): void {
        foreach (FieldGroups::getRegistered() as $handle => $fieldGroup) {
            $this->fieldGroups[ $handle ] = [
                'handle' => $handle,
                'class'  => $fieldGroup,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle))
            ];
        }
    }

    /**
     * Retrieves available form actions from the FormActions register.
     *
     * @return void
     */
    private function initialiseFormActions(): void {
        foreach (FormActions::getRegistered() as $handle => $action) {
            $this->actionPayloads[ $handle ] = [
                'handle' => $handle,
                'class'  => $action,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
            ];
        }
    }

    /**
     * Loads a form schema from a JSON string or array and return it as an array.
     *
     * @param string|array $schema
     *
     * @return array
     */
    private function loadFormSchema(string|array $schema): array {
        $this->formTitle       = $this->form->post_title ?? '';
        $this->formDescription = $this->form->post_content ?? '';
        $this->formSlug        = $this->form->post_name ?? '';
        $this->formStatus      = $this->form->post_status ?? '';

        $decoded = is_array($schema) ? $schema : json_decode($schema, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}