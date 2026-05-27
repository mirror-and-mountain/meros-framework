<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\FieldGroup;

use MM\Meros\App\Models\MerosForm as Form;
use MM\Meros\App\Models\PostMeta as FormMeta;

use MM\Meros\App\Toolbox\Forms\Concerns\ManagesFormSchema;

use MM\Meros\App\Toolbox\Forms\Helpers\Hydrator;
use MM\Meros\App\Toolbox\Forms\Helpers\Serializer;
use MM\Meros\App\Toolbox\Forms\Helpers\Utilities;

class Builder extends Component {
    /**
     * The current screen of the form-builder ui.
     *
     * @var string
     */
    public string $screen = 'preview';

    /**
     * The field currently being edited (if any).
     *
     * @var string|null
     */
    public ?string $editingRepeaterID = null;

    /**
     * The repeater field instance currently being edited (if any).
     *
     * @var Field|null
     */
    private ?Field $editingRepeaterField = null;

    /**
     * A collection of hydrated elements used in the schema.
     *
     * @var Collection|null
     */
    private ?Collection $elements = null;

    /**
     * Nav Items to be rendered in the builder's navigation bar.
     *
     * @var array
     */
    public array $navItems = [];

    /**
     * The url to return to when clicking the wordpress link in the header.
     *
     * @var string
     */
    public string $returnUrl = '';

    use ManagesFormSchema;

    public function mount(string|int|null $formID = null) {
        $this->initialiseFields();
        $this->initialiseFieldGroups();

        if ($formID) {
            $this->formID = $formID;
            $this->form   = Form::find($formID);

            $this->navItems = [
                0 => 'Settings',
                1 => 'Canvas',
                'Preview' => get_preview_post_link($formID)
            ];

            $this->returnUrl = admin_url('edit.php?post_type=meros-form');
        } else {
            $this->makeNewForm();
        }

        $rawSchema = '';

        if ($this->form) {
            $rawSchema = $this->loadFormSchema($this->form->schema());
        } else {
            $rawSchema = $this->loadFormSchema(static::defaultFormStructureJson());
        }

        $this->schema = [
            'rows'     => Utilities::normaliseRowPayloads($rawSchema['rows'] ?? []),
            'actions'  => $rawSchema['actions'] ?? []
        ];

        $this->rowPayloads    = $this->schema['rows'] ?? [];
        $this->actionPayloads = $this->schema['actions'] ?? [];
    }

    public function render() {
        $hydrator = Hydrator::make($this->fieldTypes, $this);
        $hydratedRows = $hydrator->hydrateRowPayloads($this->rowPayloads);

        return view('meros::toolbox.forms.builder.index', [
            'formID'          => $this->formID,
            'formTitle'       => $this->formTitle,
            'formDescription' => $this->formDescription,
            'canvasRows'      => $hydratedRows,
            'editingRepeater' => $this->editingRepeaterField
        ])
            ->layout('meros::toolbox.layout', [
                'navItems'  => $this->navItems,
                'returnUrl' => $this->returnUrl
            ]);
    }

    /**
     * Updates a specific setting of the form being edited and dispatches a schema update event.
     *
     * @param string $settingKey
     * @param mixed  $settingValue
     *
     * @return void
     */
    public function updateSettings(string $settingKey, mixed $settingValue): void {
        if (property_exists($this, $settingKey)) {
            $this->{$settingKey} = $settingValue;
        }

        $this->dispatchSchemaUpdate();
    }

    /**
     * Updates the form actions and dispatches a schema update event.
     *
     * @param array $actions
     *
     * @return void
     */
    public function updateActions(array $actions): void {
        $this->schema['actions'] = $actions;
        $this->actionPayloads = $actions;

        $this->dispatchSchemaUpdate();
    }

    /**
     * Updates the form schema rows with new row payloads.
     *
     * @param array $updatedSchemaRows
     *
     * @return void
     */
    public function updateRows(array $updatedSchemaRows): void {
        $this->schema['rows'] = $updatedSchemaRows;
        $this->rowPayloads    = $updatedSchemaRows;

        $this->dispatchSchemaUpdate();
    }

    /**
     * Retrieves the form's settings as an array.
     *
     * @return array
     */
    public function getFormSettings(): array {
        return [
            'title'       => $this->formTitle,
            'description' => $this->formDescription,
            'slug'        => $this->formSlug,
            'status'      => $this->formStatus
        ];
    }

    /**
     * Retrieves the form's actions as an array.
     *
     * @return array
     */
    public function getFormActions(): array {
        return $this->schema['actions'] ?? [];
    }

    /**
     * Retrieves the form schema rows for rendering in the canvas.
     *
     * @return array
     */
    #[Renderless]
    public function getRows(): array {
        return $this->schema['rows'] ?? [];
    }

    /**
     * Adds a hydrated element to the collection of elements used in the schema.
     *
     * @param Field|FieldGroup $element
     *
     * @return void
     */
    public function addElement(Field|FieldGroup $element): void {
        $this->elements = $this->elements ?? collect([]);
        $this->elements->push($element);
    }

    /**
     * Sets the ID of the repeater currently being edited.
     *
     * @param string|null $fieldID The repeater's field ID or null to clear the editing state.
     *
     * @return void
     */
    public function setEditingRepeaterID(string|null $fieldID = null): void {
        $this->editingRepeaterID = $fieldID;

        if ($fieldID === null) {
            $this->editingRepeaterField = null;
        } else {
            $this->getEditingRepeaterField($fieldID);
        }

        $this->dispatchSchemaUpdate();
    }

    /**
     * Add a new field to the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldType
     * @param int    $position
     *
     * @return void
     */
    public function addRepeaterField(string $repeaterID, string $fieldType = 'text', int $position = 0): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $id = Str::uuid()->toString();

        $instance = Hydrator::make($this->fieldTypes, $this)->hydrateFieldPayload(
            [
                'handle' => $fieldType,
                'properties' => [
                    'id'       => 'field_' . Str::substr($id, 0, 8),
                    'label'    => ucfirst($fieldType),
                    'name'     => Str::slug($fieldType) . '_' . Str::substr($id, 0, 8),
                    'helpText' => '',
                    'value'    => '',
                    'required' => false,
                    'disabled' => false,
                    'width'    => 'full'
                ]
            ]
        );

        $repeater->attach([$instance], $position);

        $this->saveRepeater($repeater);
    }

    /**
     * Updates the configuration of a field within the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldID
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function updateRepeaterField(string $repeaterID, string $fieldID, string $property, mixed $value): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $field = $repeater->getFields()->map(function($field) use ($fieldID) {
            return $field->getId() === $fieldID ? $field : null;
        })->filter()->first();

        if (!$field) {
            return;
        }

        if (method_exists($field, $property)) {
            $field->{$property}($value);
        }

        $this->saveRepeater($repeater);
    }

    /**
     * Removes a field from the repeater currently being edited.
     *
     * @param string $repeaterID
     * @param string $fieldID
     *
     * @return void
     */
    public function removeRepeaterField(string $repeaterID, string $fieldID): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $field = $repeater->getFields()->map(function($field) use ($fieldID) {
            return $field->getId() === $fieldID ? $field : null;
        })->filter()->first();

        if (!$field) {
            return;
        }

        $repeater->detach($field);
        $this->saveRepeater($repeater);
    }

    /**
     * Moves a field within the repeater currently being edited to a new position.
     *
     * @param string  $repeaterID
     * @param string  $fieldID
     * @param integer $newPosition
     *
     * @return void
     */
    public function moveRepeaterField(string $repeaterID, string $fieldID, int $newPosition): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        if ($newPosition < 0) {
            $newPosition = 0;
        }

        $fields = $repeater->getFields();

        if ($newPosition >= $fields->count()) {
            $newPosition = $fields->count() - 1;
        }

        $fieldIndex = $fields->search(function($field) use ($fieldID) {
            return $field->getId() === $fieldID;
        });

        if ($fieldIndex === false) {
            return;
        }

        $field = $fields[$fieldIndex];
        $fields->splice($fieldIndex, 1);
        $fields->splice($newPosition, 0, [$field]);
        $repeater->refresh($fields->all());

        $this->saveRepeater($repeater);
    }

    /**
     * Updates the value of the repeater currently being edited.
     *
     * @param array $value
     *
     * @return void
     */
    public function updateRepeaterDefaultValue(string $repeaterID, array $value): void {
        $repeater = $this->getEditingRepeaterField($repeaterID);

        if (!$repeater) {
            return;
        }

        $repeater->default($value);
        $this->saveRepeater($repeater);
    }

    /**
     * Saves the updated repeater field back into the form schema.
     *
     * @param Field $updatedRepeater The updated repeater field instance to save.
     *
     * @return void
     */
    private function saveRepeater(Field $updatedRepeater): void {
        $payload = $updatedRepeater->toJson();
        
        $existing = null;
        foreach ($this->rowPayloads as $rowIndex => $row) {
            if (($row['_type'] ?? null) === 'fields') {
                foreach ($row['fields'] ?? [] as $fieldIndex => $field) {
                    if (($field['handle'] ?? null) === 'repeater' && ($field['properties']['id'] ?? null) === $payload['properties']['id']) {
                        $existing = [
                            'rowIndex' => $rowIndex,
                            'fieldIndex' => $fieldIndex
                        ];
                        break 2;
                    }
                }
            } elseif (($row['_type'] ?? null) === 'group') {
                foreach ($row['group']['rows'] ?? [] as $groupRowIndex => $groupRow) {
                    foreach ($groupRow['fields'] ?? [] as $fieldIndex => $field) {
                        if (($field['handle'] ?? null) === 'repeater' && ($field['properties']['id'] ?? null) === $payload['properties']['id']) {
                            $existing = [
                                'rowIndex' => $rowIndex,
                                'groupRowIndex' => $groupRowIndex,
                                'fieldIndex' => $fieldIndex
                            ];
                            break 3;
                        }
                    }
                }
            }
        }

        if (!$existing) {
            return;
        }

        if (isset($existing['groupRowIndex'])) {
            $this->rowPayloads[$existing['rowIndex']]['group']['rows'][$existing['groupRowIndex']]['fields'][$existing['fieldIndex']] = $payload;
        } else {
            $this->rowPayloads[$existing['rowIndex']]['fields'][$existing['fieldIndex']] = $payload;
        }

        $this->dispatchSchemaUpdate();
        session()->flash('updateStatus', 'Repeater updated!');
    }

    /**
     * Saves the form and its configuration to the database.
     *
     * @return void
     */
    public function saveForm(): void {
        if (!$this->form) {
            return;
        }

        $serializer = Serializer::make(Hydrator::make($this->fieldTypes, $this));

        $serializedSchema = [
            'settings' => $this->schema['settings'] ?? [],
            'rows'     => $serializer->serializeFormSchema($this->rowPayloads ?? [])
        ];
        
        $this->form->update([
            'post_title'   => $this->formTitle ?: 'Untitled Form',
            'post_name'    => Str::slug($this->formSlug ?: $this->formTitle ?: 'untitled-form'),
            'post_content' => wp_kses_post($this->formDescription ?: ''),
        ]);

        $this->form->meta()->updateOrCreate(
            ['meta_key'   => '_meros_form_meta'],
            ['meta_value' => json_encode([
                'schema'  => $serializedSchema
            ])]
        );

        if ($this->editingRepeaterID) {
            $this->setEditingRepeaterID($this->editingRepeaterID);
        }

        $this->dispatchSchemaUpdate();
        session()->flash('updateStatus', 'Form successfully saved!');
    }

    /**
     * Default JSON structure for a simple contact form.
     *
     * @return string
     */
    public static function defaultFormStructureJson(): string {
        return '{
            "type": "form",
            "elements": [],
            "rows": [
                {
                    "position": 0,
                    "type": "group",
                    "group": {
                        "id": "group-1",
                        "handle": "test-group",
                        "title": "Test Group",
                        "description": "This is a test group.",
                        "rows": []
                    }
                },
                {
                    "position": 1,
                    "type": "fields",
                    "fields": [
                        {
                            "handle": "text",
                            "properties": {
                                "id": "first-name",
                                "label": "First Name",
                                "name": "first_name",
                                "helpText": "",
                                "helpTextPosition": "bottom",
                                "value": "",
                                "required": true,
                                "disabled": false,
                                "width": "half"
                            }
                        },
                        {
                            "handle": "text",
                            "properties": {
                                "id": "last-name",
                                "handle": "text",
                                "label": "Last Name",
                                "name": "last_name",
                                "helpText": "",
                                "helpTextPosition": "bottom",
                                "value": "",
                                "required": true,
                                "disabled": false,
                                "width": "half"
                            }
                        }
                    ]
                },
                {
                    "position": 2,
                    "type": "fields",
                    "fields": [
                        {
                            "handle": "text",
                            "properties": {
                                "id": "email",
                                "handle": "text",
                                "label": "Email",
                                "name": "email",
                                "helpText": "",
                                "helpTextPosition": "bottom",
                                "value": "",
                                "required": true,
                                "disabled": false,
                                "width": "full"
                            }
                        }
                    ]
                },
                {
                    "position": 3,
                    "type": "fields",
                    "fields": [
                        {
                            "handle": "textarea",
                            "properties": {
                                "id": "message",
                                "handle": "textarea",
                                "label": "Message",
                                "name": "message",
                                "helpText": "",
                                "helpTextPosition": "bottom",
                                "value": "",
                                "required": true,
                                "disabled": false,
                                "width": "full"
                            }
                        }
                    ]
                }
            ]
        }';
    }

    /**
     * Dispatches a schema update event with the current row payloads and advanced select field configurations.
     *
     * @return void
     */
    private function dispatchSchemaUpdate(): void {
        $advancedSelectPayloads = $this->getAdvancedSelectFields($this->rowPayloads);
        $richTextPayloads = $this->getRichTextPayloads();

        $ignoredFields = array_merge(
            $advancedSelectPayloads,
            $richTextPayloads
        );

        $this->dispatch('schema-updated', [
            'rows'             => $this->rowPayloads,
            'richTextPayloads' => $richTextPayloads,
            'ignoredFields'    => $ignoredFields
        ]);
    }

    /**
     * Retrieves the repeater field instance currently being edited.
     *
     * @param string $repeaterID The ID of the repeater field to retrieve.
     *
     * @return Field|null
     */
    private function getEditingRepeaterField(string $repeaterID): ?Field {
        $repeater = null;

        foreach($this->rowPayloads as $row) {
            if (($row['_type'] ?? null) === 'fields') {
                foreach ($row['fields'] ?? [] as $field) {
                    if (($field['handle'] ?? null) === 'repeater' && ($field['properties']['id'] ?? null) === $repeaterID) {
                        $repeater = Hydrator::make($this->fieldTypes, $this)->hydrateFieldPayload($field);
                        break 2;
                    }
                }
            } elseif (($row['_type'] ?? null) === 'group') {
                foreach ($row['group']['rows'] ?? [] as $groupRow) {
                    foreach ($groupRow['fields'] ?? [] as $field) {
                        if (($field['handle'] ?? null) === 'repeater' && ($field['properties']['id'] ?? null) === $repeaterID) {
                            $repeater = Hydrator::make($this->fieldTypes, $this)->hydrateFieldPayload($field);
                            break 3;
                        }
                    }
                }
            }
        }

        $this->editingRepeaterField = $repeater;
        return $repeater;
    }

    /**
     * Creates a new form post and redirects to its builder page.
     *
     * @return void
     */
    private function makeNewForm(): void {
        $newFormId = wp_insert_post([
            'post_title'   => 'Untitled Form',
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'meros-form',
        ]);

        $defaultMeta = [
            'schema' => [
                'settings' => [],
                'rows' => []
            ]
        ];

        FormMeta::create([
            'post_id' => $newFormId,
            'meta_key' => '_meros_form_meta',
            'meta_value' => json_encode($defaultMeta)
        ]);

        if (!is_int($newFormId)) {
            abort(500, 'Failed to create new form.');
        }

        $this->redirect(route('meros.toolbox.form-builder.edit', ['formID' => $newFormId]));
    }
}