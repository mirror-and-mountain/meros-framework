<?php

namespace MM\Meros\App\Toolbox;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Livewire\Component;

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
    public function mount(): void {
        $this->elements = new Collection([]);

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
}
