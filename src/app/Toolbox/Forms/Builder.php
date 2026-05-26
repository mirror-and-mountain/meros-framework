<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\FieldGroup;

use MM\Meros\App\Models\MerosForm as Form;
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
    public array $navItems = [
        'Canvas', 
        'Preview', 
        'Settings', 
    ];

    public bool $test = false;

    private array $hydratedRows = [];

    use ManagesFormSchema;

    public function mount($formID = null) {
        $this->initialiseFields();
        $this->initialiseFieldGroups();

        if ($formID) {
            $this->formID = $formID;
            $this->form   = Form::find($formID);
        }

        $rawSchema = '';

        if ($this->form) {
            $this->formTitle = $this->form->post_title;
            $this->formDescription = $this->form->post_content;
            $rawSchema = $this->loadFormSchema($this->form->schema());
        } else {
            $rawSchema = $this->loadFormSchema(static::defaultFormStructureJson());
        }

        $this->schema = [
            'rows'     => Utilities::normaliseRowPayloads($rawSchema['rows'] ?? []),
            'settings' => $rawSchema['settings'] ?? []
        ];

        $this->settings    = $this->schema['settings'] ?? [];
        $this->rowPayloads = $this->schema['rows'] ?? [];
        
    }

    public function render() {
        $hydrator = Hydrator::make($this->fieldTypes, $this);
        $hydratedRows = $hydrator->hydrateRowPayloads($this->rowPayloads);
        $this->hydratedRows = $hydratedRows;

        return view('meros::toolbox.forms.builder.index', [
            'formID' => $this->formID,
            'canvasRows' => $hydratedRows,
            'editingRepeater' => $this->editingRepeaterField
        ])
            ->layout('meros::toolbox.layout', [
                'navItems' => $this->navItems
            ]);
    }

    /**
     * Updates the form schema rows with new row payloads.
     *
     * @param array $updatedSchemaRows
     *
     * @return void
     */
    public function updateSchemaRows(array $updatedSchemaRows): void {
        $this->schema['rows'] = $updatedSchemaRows;
        $this->rowPayloads    = $updatedSchemaRows;

        $this->dispatchSchemaUpdate();
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
     * Retrieves the form settings for rendering in the settings panel.
     *
     * @return array
     */
    #[Renderless]
    public function getSettings(): array {
        return $this->schema['settings'] ?? [];
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
        $serializer = Serializer::make(Hydrator::make($this->fieldTypes, $this));

        $serializedSchema = [
            'settings' => $this->schema['settings'] ?? [],
            'rows'     => $serializer->serializeFormSchema($this->rowPayloads ?? [])
        ];
        
        if (!$this->form) {
            $this->formID = wp_insert_post([
                'post_title'   => $this->formTitle ?: 'Untitled Form',
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'meros-form',
            ]);

            $this->form = Form::find($this->formID);
        } 
        
        else {
            $this->form->update([
                'post_title'   => $this->formTitle ?: 'Untitled Form',
                'post_content' => '',
            ]);
        }

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
        $advancedSelects = $this->getAdvancedSelectFields($this->rowPayloads);

        $this->dispatch('schema-updated', [
            'rows'             => $this->rowPayloads,
            'richTextPayloads' => $this->getRichTextPayloads(),
            'advancedSelects'  => $advancedSelects
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
     * Retrieves advanced select fields from the form schema's row payloads.
     *
     * @param array $rows
     *
     * @return array
     */
    private function getAdvancedSelectFields(array $rows): array {
        $advancedSelects = [];

        foreach ($rows as $row) {
            if ($row['group'] ?? null) {
                foreach ($row['group']['rows'] ?? [] as $groupRow) {
                    $advancedSelects = array_merge(
                        $advancedSelects,
                        $this->extractAdvancedSelects($groupRow['fields'] ?? [])
                    );
                }
            } else {
                $advancedSelects = array_merge(
                    $advancedSelects,
                    $this->extractAdvancedSelects($row['fields'] ?? [])
                );
            }
        }

        return $advancedSelects;
    }

    /**
     * Extracts advanced select fields from the schema rows.
     *
     * @param array $fields
     *
     * @return array
     */
    private function extractAdvancedSelects(array $fields): array {
        $advancedSelects = [];

        foreach ($fields as $field) {
            if (in_array($field['handle'], ['select', 'multi_select']) && 
                ($field['properties']['advanced'] ?? null) === true) 
            {
                $advancedSelects[] = $this->buildAdvancedSelectConfig($field['properties']);
            } 
            
            else if ($field['handle'] === 'repeater') {
                foreach ($field['fields'] ?? [] as $repeaterField) {
                    if (in_array($repeaterField['handle'], ['select', 'multi_select']) && 
                        ($repeaterField['properties']['advanced'] ?? null) === true) 
                    {
                        $advancedSelects[] = $this->buildAdvancedSelectConfig($repeaterField['properties']);
                    }
                }
            }
        }

        return $advancedSelects;
    }

    /**
     * Builds an advanced select configuration from field properties.
     *
     * @param array $properties
     *
     * @return array
     */
    private function buildAdvancedSelectConfig(array $properties): array {
        return [
            'id'               => $properties['id'],
            'label'            => $properties['label'] ?? '',
            'name'             => $properties['name'] ?? '',
            'helpText'         => $properties['helpText'] ?? '',
            'helpTextPosition' => $properties['helpTextPosition'] ?? 'top',
            'required'         => $properties['required'] ?? false,
            'disabled'         => $properties['disabled'] ?? false
        ];
    }
}