<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use MM\Meros\App\Models\MerosForm as Form;
use MM\Meros\App\Toolbox\Forms\Concerns\ManagesFormSchema;

use MM\Meros\App\Toolbox\Forms\Helpers\Hydrator;
use MM\Meros\App\Toolbox\Forms\Helpers\Serializer;
use MM\Meros\App\Toolbox\Forms\Helpers\Normaliser;

class Builder extends Component {
    /**
     * The ID of the form being edited (if any).
     *
     * @var int|null
     */
    public ?string $currentlyEditingFieldID = null;

    /**
     * Nav Items to be rendered in the builder's navigation bar.
     *
     * @var array
     */
    public array $navItems = [
        'Canvas', 
        'Preview', 
        'Settings', 
        'Save'
    ];

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
            $rawSchema = $this->loadFormSchema($this->form->schema());
        } else {
            $rawSchema = $this->loadFormSchema(static::defaultFormStructureJson());
        }

        $this->schema = [
            'rows'     => Normaliser::normaliseRowPayloads($rawSchema['rows'] ?? []),
            'settings' => $rawSchema['settings'] ?? []
        ];

        $this->settings    = $this->schema['settings'] ?? [];
        $this->rowPayloads = $this->schema['rows'] ?? [];
        
    }

    public function render() {
        $hydratedRows = Hydrator::hydrateRowPayloads($this->rowPayloads, $this->fieldTypes);

        return view('meros::toolbox.forms.builder.index', [
            'canvasRows' => $hydratedRows
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
    }

    /**
     * Retrieves the form schema rows for rendering in the canvas.
     *
     * @return array
     */
    #[Renderless]
    public function getRows(): array {
        return $this->schema['rows'] ?? [
            'test' => 'rows'
        ];
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
     * Saves the form and its configuration to the database.
     *
     * @return void
     */
    public function saveForm(): void {
        $serializedSchema = [
            'settings' => $this->schema['settings'] ?? [],
            'rows'     => Serializer::serializeFormSchema($this->rowPayloads, $this->fieldTypes)
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
}