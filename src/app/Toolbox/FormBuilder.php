<?php

namespace MM\Meros\App\Toolbox;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Livewire\Component;

use MM\Meros\App\Models\MerosForm;

use MM\Meros\App\Toolbox\Helpers\FormStructureSerializer;
use MM\Meros\App\Toolbox\Helpers\PayloadHydrator;
use MM\Meros\App\Toolbox\Helpers\RowMutator;

use MM\Meros\App\Toolbox\Concerns\DispatchesTomSelectEvents;
use MM\Meros\App\Toolbox\Concerns\ManagesCanvasFields;
use MM\Meros\App\Toolbox\Concerns\ManagesCanvasRepeaterFields;
use MM\Meros\App\Toolbox\Concerns\ManagesFieldSettings;
use MM\Meros\App\Toolbox\Concerns\ManagesGroupRows;
use MM\Meros\App\Toolbox\Concerns\ManagesRepeaterBuilder;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;

use MM\Meros\Services\Contracts\Elements\Field;

class FormBuilder extends Component {
    /**
     * The ID of the form being edited, if applicable.
     *
     * @var string|null
     */
    public ?string $formID = null;

    /**
     * The form model being edited, if applicable.
     *
     * @var MerosForm|null
     */
    public ?MerosForm $form = null;

    /**
     * Field classes available for the form builder.
     *
     * @var array
     */
    public array $fieldTypes = [];

    /**
     * Field group classes available for the form builder.
     *
     * @var array
     */
    public array $fieldGroups = [];

    /**
     * The field categories for organising field types in the UI.
     *
     * @var array
     */
    public array $fieldCategories = [];

    /**
     * Instantiated form elements.
     *
     * @var Collection<Field>
     */
    private Collection $elements;

    /**
     * The rows of the form, each row containing up to 3 field definitions.
     *
     * @var array
     */
    public array $rows = [];

    /**
     * Legacy field group container payloads (deprecated).
     *
     * @var array
     */
    public array $groups = [];

    /**
     * The currently selected repeater field location.
     *
     * @var array<string, int|null>|null
     */
    public ?array $activeRepeater = null;

    /**
     * The currently selected repeater row index for editing.
     *
     * @var int|null
     */
    public ?int $activeRepeaterRow = null;

    /**
     * The currently selected non-repeater field location for settings.
     *
     * @var array<string, int|null>|null
     */
    public ?array $activeFieldSettings = null;

    /**
     * Reusable payload hydrator for field/group instantiation and payload factories.
     * 
     * @var PayloadHydrator
     */
    private PayloadHydrator $hydrator;

    use ManagesCanvasFields;
    use ManagesGroupRows;
    use ManagesFieldSettings;
    use ManagesRepeaterBuilder;
    use ManagesCanvasRepeaterFields;
    use DispatchesTomSelectEvents;

    /**
     * Livewire lifecycle hook for the initial request. Loads field and group types from the registry and initialises helpers.
     * 
     * @return void
     */
    public function mount(?string $formID = null): void {
        $this->elements = new Collection([]);

        $this->formID = $formID;

        foreach (Fields::getRegistered() as $handle => $fieldType) {
            $this->fieldTypes[$handle] = $fieldType;
            $category                  = $fieldType::getCategory();

            $this->fieldCategories[$category][$handle] = [
                'handle' => $handle,
                'class'  => $fieldType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
                'icon'   => $fieldType::getIcon(),
            ];
        }

        foreach (FieldGroupsRegister::getRegistered() as $handle => $groupType) {
            $this->fieldGroups[$handle] = [
                'handle' => $handle,
                'class'  => $groupType,
                'label'  => Str::title(Str::replace(['-', '_'], ' ', $handle)),
            ];
        }

        $this->initialiseHelpers();

        if ($formID) {
            $this->form = MerosForm::find($formID);

            if ($this->form) {
                $meta      = $this->form->meta->where('meta_key', 'form_structure')->first();
                $structure = $meta?->meta_value ?? $meta?->value;

                if (is_string($structure) && trim($structure) !== '') {
                    $this->loadRowsFromStructure($structure);
                } else {
                    $this->loadRowsFromStructure(self::defaultFormStructureJSONComplex());
                }
            }
        }
    }

    /**
     * Livewire lifecycle hook for subsequent requests. Ensures helper objects are re-initialised with current registry state.
     * 
     * @return void
     */
    public function booted(): void {
        $this->initialiseHelpers();
    }

    /**
     * Render the form builder interface with hydrated field and group objects.
     */
    public function render() {
        $this->validateActiveFieldSettings();

        $formRows      = $this->getHydratedRows();
        $formGroups    = [];
        $canvasRows    = [];
        $fieldVersions = [];

        foreach ($this->rows as $rowIndex => $rowPayload) {
            if (RowMutator::isGroupRow($rowPayload)) {
                $groupPayload = $rowPayload['group'] ?? [];
                $groupRows    = $this->hydrator->hydratePayloadRows($groupPayload['rows'] ?? []);
                $groupObject  = $this->hydrator->makeFieldGroupFromPayload($groupPayload);
                $formGroups[] = [
                    'rowIndex'    => $rowIndex,
                    'id'          => $groupPayload['id'] ?? Str::uuid()->toString(),
                    'handle'      => $groupPayload['handle'] ?? '',
                    'title'       => $groupPayload['title'] ?? 'Untitled Section',
                    'description' => $groupPayload['description'] ?? '',
                    'rows'        => $groupRows,
                ];

                foreach (($groupPayload['rows'] ?? []) as $groupRowPayload) {
                    foreach ((array) $groupRowPayload as $fieldPayload) {
                        if (is_array($fieldPayload) && isset($fieldPayload['id'])) {
                            $fieldVersions[$fieldPayload['id']] = $fieldPayload['_fieldVersion'] ?? 0;
                        }
                    }
                }

                $canvasRows[] = [
                    '_type'    => 'group',
                    'rowIndex' => $rowIndex,
                    'group'    => [
                        'id'          => $groupPayload['id'] ?? Str::uuid()->toString(),
                        'object'      => $groupObject,
                        'title'       => $groupPayload['title'] ?? 'Untitled Section',
                        'description' => $groupPayload['description'] ?? '',
                        'rows'        => $groupRows,
                    ],
                ];

                continue;
            }

            foreach ((array) $rowPayload as $fieldPayload) {
                if (is_array($fieldPayload) && isset($fieldPayload['id'])) {
                    $fieldVersions[$fieldPayload['id']] = $fieldPayload['_fieldVersion'] ?? 0;
                }
            }

            $hydratedFields = $this->hydrator->hydratePayloadRows([$rowPayload]);
            $canvasRows[] = [
                '_type'    => 'fields',
                'rowIndex' => $rowIndex,
                'fields'   => $hydratedFields[0] ?? [],
            ];
        }

        return view('meros::toolbox.form-builder.index', [
            'fieldCategories'          => $this->fieldCategories, // For the field type selector in the sidebar
            'fieldGroups'              => $this->fieldGroups, // For the field group type selector in the sidebar
            'formRows'                 => $formRows, // Hydrated field objects for rendering the canvas
            'formGroups'               => $formGroups, // Hydrated group objects with their rows and fields for rendering the canvas
            'canvasRows'               => $canvasRows, // Normalised row data for rendering the canvas (group rows with group metadata, field rows with hydrated field objects)
            'fieldVersions'            => $fieldVersions, // For tracking field payload versions to prevent overwriting changes when mutating fields
            'activeRepeaterField'      => $this->getActiveRepeaterViewModel(), // For the repeater builder sidebar when a repeater field is active
            'activeRepeater'           => $this->activeRepeater, // For determining if the repeater builder sidebar should be open and which repeater field it is associated with
            'activeRepeaterRow'        => $this->activeRepeaterRow, // For determining which repeater row is active for editing in the repeater builder sidebar
            'activeFieldSettingsModel' => $this->getActiveFieldSettingsViewModel(), // For the field settings sidebar when a non-repeater field is active
        ])
            ->layout('meros::toolbox.layout', ['title' => 'Form Builder']);
    }

    /**
     * Build a normalized form structure for persistence.
     *
     * @return array
     */
    public function getFormStructure(): array {
        $serializer = new FormStructureSerializer($this->hydrator);

        return $serializer->buildFormStructure($this->rows);
    }

    /**
     * Export the current form structure as JSON for storage.
     *
     * @param bool $pretty
     *
     * @return string
     */
    public function getFormStructureJson(bool $pretty = false): string {
        $flags = JSON_UNESCAPED_SLASHES;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        dd(json_encode($this->getFormStructure(), $flags) ?: '{"rows":[]}');

        return json_encode($this->getFormStructure(), $flags) ?: '{"rows":[]}';
    }

    /**
     * Load builder rows from a persisted schema object/string.
     *
     * @param string|array $structure
     *
     * @return void
     */
    public function loadRowsFromStructure(string|array $structure): void {
        $this->rows = $this->extractRowsFromStructure($structure);

        if (isset($this->hydrator)) {
            // Force instantiation pass so loaded schema fields are hydrated immediately.
            $this->getHydratedRows();
        }
    }

    /**
     * Parse stored schema and convert rows to internal builder payload rows.
     *
     * @param string|array $structure
     *
     * @return array
     */
    private function extractRowsFromStructure(string|array $structure): array {
        $decoded = is_array($structure) ? $structure : json_decode($structure, true);

        if (!is_array($decoded) || !is_array($decoded['rows'] ?? null)) {
            return [];
        }

        return $this->normaliseSchemaRows($decoded['rows']);
    }

    /**
     * Accept schema rows and map them into row payloads expected by trait methods.
     *
     * @param array $rows
     *
     * @return array
     */
    private function normaliseSchemaRows(array $rows): array {
        $normalisedRows = [];

        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (RowMutator::isGroupRow($row)) {
                $normalisedRows[] = $row;
                continue;
            }

            if (($row['type'] ?? null) === 'group' && is_array($row['group'] ?? null)) {
                $groupRows = [];

                foreach (array_values($row['group']['rows'] ?? []) as $groupRow) {
                    if (is_array($groupRow['fields'] ?? null)) {
                        $groupRows[] = $this->normaliseFieldPayloads($groupRow['fields']);
                        continue;
                    }

                    if (is_array($groupRow)) {
                        $groupRows[] = $this->normaliseFieldPayloads($groupRow);
                    }
                }

                $normalisedRows[] = [
                    '_type' => 'group',
                    'group' => [
                        'id'          => $row['group']['id'] ?? Str::uuid()->toString(),
                        'handle'      => $row['group']['handle'] ?? '',
                        'title'       => $row['group']['title'] ?? 'Untitled Section',
                        'description' => $row['group']['description'] ?? '',
                        'rows'        => $groupRows,
                    ],
                ];

                continue;
            }

            if (is_array($row['fields'] ?? null)) {
                $normalisedRows[] = $this->normaliseFieldPayloads($row['fields']);
                continue;
            }

            $normalisedRows[] = $this->normaliseFieldPayloads($row);
        }

        return $normalisedRows;
    }

    /**
     * Normalise raw field payload arrays so hydration can instantiate by handle.
     *
     * @param array $fields
     *
     * @return array
     */
    private function normaliseFieldPayloads(array $fields): array {
        $normalisedFields = [];

        foreach (array_values($fields) as $fieldPayload) {
            if (!is_array($fieldPayload)) {
                continue;
            }

            $handle = $fieldPayload['handle'] ?? null;

            if (!is_string($handle) || trim($handle) === '') {
                continue;
            }

            $fieldPayload['id'] = $fieldPayload['id'] ?? Str::uuid()->toString();
            $normalisedFields[] = $fieldPayload;
        }

        return $normalisedFields;
    }

    /**
     * Return the payload for a top-level or grouped field location.
     *
     * @param array<string, int|null> $location
     * 
     * @return array|null
     */
    private function getFieldPayloadAt(array $location): ?array {
        $groupRowIndex = $location['groupRowIndex'] ?? null;
        $rowIndex      = $location['rowIndex'] ?? null;
        $fieldIndex    = $location['fieldIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return null;
        }

        if ($groupRowIndex === null) {
            return $this->rows[$rowIndex][$fieldIndex] ?? null;
        }

        if (!is_int($groupRowIndex) || !RowMutator::isValidGroupRowIndex($this->rows, $groupRowIndex)) {
            return null;
        }

        return $this->rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex] ?? null;
    }

    /**
     * Mutate a field payload at the given location.
     *
     * @param array<string, int|null> $location
     * @param callable                $mutator A callback that receives the current payload and returns the updated payload.
     * 
     * @return bool Returns false if the location is invalid or the payload could not be mutated.
     */
    private function mutateFieldPayloadAt(array $location, callable $mutator): bool {
        $rows          = $this->rows;
        $groupRowIndex = $location['groupRowIndex'] ?? null;
        $rowIndex      = $location['rowIndex'] ?? null;
        $fieldIndex    = $location['fieldIndex'] ?? null;

        if (!is_int($rowIndex) || !is_int($fieldIndex)) {
            return false;
        }

        if ($groupRowIndex === null) {
            if (!isset($rows[$rowIndex][$fieldIndex]) || !is_array($rows[$rowIndex][$fieldIndex])) {
                return false;
            }

            $rows[$rowIndex][$fieldIndex] = $mutator($rows[$rowIndex][$fieldIndex]);
            $this->rows = $rows;

            return true;
        }

        if (!is_int($groupRowIndex) || !RowMutator::isValidGroupRowIndex($rows, $groupRowIndex)) {
            return false;
        }

        if (!isset($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex]) || !is_array($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex])) {
            return false;
        }

        $rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex] = $mutator($rows[$groupRowIndex]['group']['rows'][$rowIndex][$fieldIndex]);
        $this->rows = $rows;

        return true;
    }

    /**
     * Hydrate field objects from row payloads for rendering.
     *
     * @return array<int, array<int, Field>>
     */
    private function getHydratedRows(): array {
        $fieldRows    = Arr::where($this->rows, fn ($row) => is_array($row) && !RowMutator::isGroupRow($row));
        $hydratedRows = $this->hydrator->hydratePayloadRows($fieldRows);

        $this->elements = new Collection(Arr::flatten($hydratedRows, 1));

        return $hydratedRows;
    }

    /**
     * Ensure helper objects are constructed with current registry state.
     * 
     * @return void
     */
    private function initialiseHelpers(): void {
        $this->hydrator = new PayloadHydrator($this->fieldTypes, $this->fieldGroups);
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
                    "type": "fields",
                    "fields": [
                        {
                            "id": "first-name",
                            "handle": "text",
                            "label": "First Name",
                            "name": "first_name",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        },
                        {
                            "id": "last-name",
                            "handle": "text",
                            "label": "Last Name",
                            "name": "last_name",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        }
                    ]
                },
                {
                    "position": 1,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "email",
                            "handle": "text",
                            "label": "Email",
                            "name": "email",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "full",
                            "variation": ""
                        }
                    ]
                },
                {
                    "position": 2,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "message",
                            "handle": "textarea",
                            "label": "Message",
                            "name": "message",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "full",
                            "variation": ""
                        }
                    ]
                }
            ]
        }';
    }

    /**
     * Default JSON structure for a complex test form with groups and repeaters.
     *
     * @return string
     */
    public static function defaultFormStructureJSONComplex(): string {
        return '{
            "type": "form",
            "elements": [],
            "rows": [
                {
                    "position": 0,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "contact-first-name",
                            "handle": "text",
                            "label": "First Name",
                            "name": "first_name",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        },
                        {
                            "id": "contact-last-name",
                            "handle": "text",
                            "label": "Last Name",
                            "name": "last_name",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        }
                    ]
                },
                {
                    "position": 1,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "contact-email",
                            "handle": "text",
                            "label": "Email",
                            "name": "email",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "",
                            "required": true,
                            "disabled": false,
                            "style": "nice",
                            "width": "full",
                            "variation": ""
                        }
                    ]
                },
                {
                    "position": 2,
                    "type": "group",
                    "group": {
                        "id": "group-company-details",
                        "handle": "",
                        "title": "Company Details",
                        "description": "Information about the organization.",
                        "rows": [
                            {
                                "position": 0,
                                "fields": [
                                    {
                                        "id": "company-name",
                                        "handle": "text",
                                        "label": "Company Name",
                                        "name": "company_name",
                                        "helpText": "",
                                        "helpTextPosition": "bottom",
                                        "value": "",
                                        "required": false,
                                        "disabled": false,
                                        "style": "nice",
                                        "width": "half",
                                        "variation": ""
                                    },
                                    {
                                        "id": "company-size",
                                        "handle": "select",
                                        "label": "Company Size",
                                        "name": "company_size",
                                        "helpText": "",
                                        "helpTextPosition": "bottom",
                                        "value": "",
                                        "options": {
                                            "1_10": "1-10",
                                            "11_50": "11-50",
                                            "51_200": "51-200",
                                            "200_plus": "200+"
                                        },
                                        "required": false,
                                        "disabled": false,
                                        "style": "nice",
                                        "width": "half",
                                        "variation": ""
                                    }
                                ]
                            },
                            {
                                "position": 1,
                                "fields": [
                                    {
                                        "id": "company-about",
                                        "handle": "textarea",
                                        "label": "About Company",
                                        "name": "company_about",
                                        "helpText": "",
                                        "helpTextPosition": "bottom",
                                        "value": "",
                                        "required": false,
                                        "disabled": false,
                                        "style": "nice",
                                        "width": "full",
                                        "variation": ""
                                    }
                                ]
                            }
                        ]
                    }
                },
                {
                    "position": 3,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "project-history",
                            "handle": "repeater",
                            "label": "Project History",
                            "name": "project_history",
                            "helpText": "Add one or more past projects.",
                            "helpTextPosition": "bottom",
                            "value": [
                                {
                                    "project_name": "Website Refresh",
                                    "project_notes": "Updated marketing pages"
                                }
                            ],
                            "required": false,
                            "disabled": false,
                            "style": "nice",
                            "width": "full",
                            "variation": "",
                            "fields": [
                                {
                                    "id": "project-name-field",
                                    "handle": "text",
                                    "label": "Project Name",
                                    "name": "project_name",
                                    "style": "nice",
                                    "width": "half"
                                },
                                {
                                    "id": "project-notes-field",
                                    "handle": "textarea",
                                    "label": "Notes",
                                    "name": "project_notes",
                                    "style": "nice",
                                    "width": "half"
                                }
                            ],
                            "defaultRows": [
                                {
                                    "project_name": "Website Refresh",
                                    "project_notes": "Updated marketing pages"
                                }
                            ]
                        }
                    ]
                },
                {
                    "position": 4,
                    "type": "fields",
                    "fields": [
                        {
                            "id": "preferred-contact-method",
                            "handle": "radio",
                            "label": "Preferred Contact Method",
                            "name": "preferred_contact_method",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": "email",
                            "options": {
                                "email": "Email",
                                "phone": "Phone",
                                "sms": "SMS"
                            },
                            "required": false,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        },
                        {
                            "id": "contact-consent",
                            "handle": "checkbox",
                            "label": "Consent to Contact",
                            "name": "contact_consent",
                            "helpText": "",
                            "helpTextPosition": "bottom",
                            "value": false,
                            "required": false,
                            "disabled": false,
                            "style": "nice",
                            "width": "half",
                            "variation": ""
                        }
                    ]
                }
            ]
        }';
    }
}
