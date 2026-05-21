<?php 

namespace MM\Meros\App\Toolbox\Forms\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Models\MerosForm as Form;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups;

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
     * @var string|null
     */
    public ?string $formID = null;

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
     * The form's settings
     *
     * @var array
     */
    public array $settings = [];

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
     * Loads a form schema from a JSON string or array and return it as an array.
     *
     * @param string|array $schema
     *
     * @return array
     */
    private function loadFormSchema(string|array $schema): array {
        $decoded = is_array($schema) ? $schema : json_decode($schema, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}