<?php

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use MM\Meros\App\Models\Form as FormModel;
use MM\Meros\App\Models\FormResponse;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Forms\FormAction as FormActionContract;

use MM\Meros\Facades\FormActions;
use MM\Meros\Facades\Framework;

class Form extends Component {
    /**
     * Indicates whether the form title should be displayed.
     *
     * @var bool
     */
    public bool $showTitle = false;

    /**
     * Indicates whether the form description should be displayed.
     *
     * @var bool
     */
    public bool $showDescription = false;

    /**
     * The ID of the form being displayed.
     *
     * @var string|int
     */
    public string|int $formID = '';

    /**
     * The form model instance associated with the form.
     *
     * @var FormModel|null
     */
    public ?FormModel $form = null;

    /**
     * The title of the form.
     *
     * @var string
     */
    public string $formTitle = '';

    /**
     * The description of the form.
     *
     * @var string
     */
    public string $formDescription = '';

    /**
     * The schema of the form, including rows and actions.
     *
     * @var array
     */
    public array $schema = [];

    /**
     * The rows of the form, each representing a form row instance.
     *
     * @var array
     */
    public array $rows = [];
    
    /**
     * The index of the currently active group page in the form.
     *
     * @var int
     */
    public int $activeGroupPage = 0;

    /**
     * The total number of group pages in the form.
     *
     * @var int
     */
    public int $totalGroupPages = 0;

    /**
     * The direction of navigation between group pages ('forward' or 'backward').
     *
     * @var string
     */
    public string $groupPageDirection = 'forward';

    /**
     * Indicates whether the form is displayed in paged view mode.
     *
     * @var bool
     */
    public bool $isPagedView = false;

    /**
     * Tracks in-progress field state between Livewire re-renders.
     *
     * @var array<string, array>
     */
    public array $fieldState = [];

    public function mount(string|int $formID, bool $showTitle = false, bool $showDescription = true, bool $isPagedView = false) {
        $this->formID          = $formID;
        $this->showTitle       = $showTitle;
        $this->showDescription = $showDescription;
        $this->isPagedView     = $isPagedView;

        if ($this->formID) {
            $this->form = FormModel::find($formID);

            if ($this->form) {
                $this->loadFormSchema();

                foreach ($this->rows as $index => $row) {
                    if (!$row instanceof FormRow) {
                        $this->rows[$index] = FormRow::initFromData(array_merge($row, ['index' => $index]));
                    }
                }

                $this->recalculateGroupPages();
            }
        }
    }

    public function render() {
        $this->recalculateGroupPages();

        return view('meros::forms.form', [
            'showTitle'       => $this->showTitle,
            'showDescription' => $this->showDescription,
            'formID'          => $this->formID,
            'formTitle'       => $this->formTitle,
            'formDescription' => $this->formDescription,
            'fields'          => $this->getInitialFormData(),
        ]);
    }

    // =========================================================================
    // Initialisation Methods
    // =========================================================================

    /**
     * Initialises form settings and schema from the form model, if it exists, 
     * or sets defaults for a new form.
     *
     * @return void
     */
    private function loadFormSchema(): void {
        if (!$this->form) {
            return;
        }

        $schema = is_array($this->form->schema) ? $this->form->schema : json_decode($this->form->schema, true);

        $this->schema = is_array($schema) ? $schema : [
            'rows'    => [],
            'actions' => []
        ];

        $this->rows = $this->schema['rows'] ?? [];
        $this->formTitle = $this->form->post_title ?? '';
        $this->formDescription = $this->form->post_content ?? '';
    }

    /**
     * Retrieves the initial form data for all fields in the form schema.
     *
     * @return array An associative array containing the initial data for each field, keyed by field ID.
     */
    private function getInitialFormData(): array {
        $initialData = [];

        $this->getFields(true)->map(function (Field $field) use (&$initialData) {
            $persistedValue = $this->fieldState[$field->id]['value'] ?? null;
            $value = array_key_exists('value', $this->fieldState[$field->id] ?? []) ? $persistedValue : $field->getValue();

            if (array_key_exists('value', $this->fieldState[$field->id] ?? [])) {
                $field->value($value);
            }

            $initialData[$field->id] = [
                'id'    => $field->id,
                'name'  => $field->getName(),
                'label' => $field->getLabel(),
                'value' => $value,
            ];

            return $initialData;
        });

        return $initialData;
    }

    /**
     * Retrieves all field instances from the form schema as a collection or an array.
     * 
     * @param bool  $skipRepeaterFields Whether to skip fields that are part of a repeater when retrieving all fields.
     * @param bool  $asArray            Whether to return the fields as an array (true) or a collection (false).
     * @param array $pluckKeys          Optional array of property keys to pluck from each field's properties when returning as an array.
     *
     * @return Collection|array
     */
    #[Renderless]
    private function getFields(bool $skipRepeaterFields = false, bool $asArray = false,  array $pluckKeys = []): Collection|array {
        $fields = collect($this->rows)
            ->filter(fn($row) => $row instanceof FormRow)
            ->flatMap(function (FormRow $row) use ($skipRepeaterFields) {
                $fields = $row->getFields();

                if (!$skipRepeaterFields) {
                    $fields = $fields->map(function (Field $field) {
                        if ($field instanceof Repeater) {
                            return $field->getFields();
                        }
                        return $field;
                    })->flatten();
                }

                return $fields;
            });

        if ($asArray && empty($pluckKeys)) {
            $fields = $fields->map(function (Field $field) {
                return array_merge(['handle' => $field->handle], $field->toJson()['properties'] ?? []);
            });
        } 
        
        else if ($asArray && !empty($pluckKeys)) {
            $fields = $fields->map(function (Field $field) use ($pluckKeys) {
                $properties = $field->toJson()['properties'] ?? [];
                $pluckedProperties = Arr::only($properties, $pluckKeys);
                return array_merge(['handle' => $field->handle], $pluckedProperties);
            });
        }

        return $asArray ? $fields->toArray() : $fields;
    }

    // =========================================================================
    // Form Submission Methods
    // =========================================================================

    /**
     * Handles the submission of the form data.
     *
     * @param array $data The submitted form data.
     *
     * @return void
     */
    public function submitForm(array $data): void {
        $this->resetErrorBag();
        $this->syncFormState($data);

        [$sanitisedData, $validationErrors] = $this->validateAndSanitiseSubmission($this->fieldState);

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $fieldId => $messages) {
                foreach ($messages as $message) {
                    $this->addError("fields.{$fieldId}", $message);
                }
            }

            $errorPageIndex = $this->isPagedView
                ? $this->navigateToFirstValidationErrorPage($validationErrors)
                : null;

            $message = 'Please review the highlighted fields and try again.';

            if ($errorPageIndex !== null && $this->totalGroupPages > 1) {
                $pageNumber = $errorPageIndex + 1;
                $message = "Please review the highlighted fields. You have been taken to page {$pageNumber} where the first error was found.";
            }

            session()->flash('meros_form_status', [
                'type' => 'validation-error',
                'message' => $message,
                'errors' => $validationErrors,
                'errorPage' => $errorPageIndex,
            ]);

            return;
        }

        $this->fieldState = $sanitisedData;

        try {
            $response = FormResponse::create([
                'form_id'  => $this->formID,
                'response' => $this->fieldState,
            ]);

            $this->executeFormActions($this->fieldState, $response);

            $this->resetFormAfterSuccessfulSubmit();

            session()->flash('meros_form_status', [
                'type' => 'success',
                'message' => 'Form submitted successfully.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            session()->flash('meros_form_status', [
                'type' => 'system-error',
                'message' => 'We could not save your submission right now. Please try again.',
            ]);
        }
    }

    /**
     * Executes form actions configured in the form schema.
     *
     * @param array $submissionState
     * @param FormResponse $response
     * @return void
     */
    private function executeFormActions(array $submissionState, FormResponse $response): void {
        $actions = $this->schema['actions'] ?? [];

        if (!is_array($actions) || $actions === []) {
            return;
        }

        $submissionByFieldName = collect($submissionState)
            ->filter(fn ($field) => is_array($field))
            ->mapWithKeys(function ($field) {
                $name = is_string($field['name'] ?? null) ? $field['name'] : null;

                if ($name === null || $name === '') {
                    return [];
                }

                return [$name => $field['value'] ?? null];
            })
            ->toArray();

        foreach ($actions as $index => $actionRow) {
            if (!is_array($actionRow)) {
                continue;
            }

            $actionHandle = trim((string) ($actionRow['action'] ?? ''));

            if ($actionHandle === '') {
                continue;
            }

            $rawConfig = $actionRow['__configuration'] ?? [];
            $config = [];

            if (is_string($rawConfig) && $rawConfig !== '') {
                $decoded = json_decode($rawConfig, true);
                $config = is_array($decoded) ? $decoded : [];
            } elseif (is_array($rawConfig)) {
                $config = $rawConfig;
            }

            try {
                $action = FormActions::checkout(Framework::get())->makeFrom($actionHandle);

                if (!$action instanceof FormActionContract) {
                    continue;
                }

                $action->config($config)->execute($submissionByFieldName, [
                    'form' => $this->form,
                    'form_id' => $this->formID,
                    'response' => $response,
                    'raw_submission' => $submissionState,
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Validates and sanitises submitted field payload against registered field definitions.
     *
     * @param array $data
     *
     * @return array{0: array, 1: array}
     */
    private function validateAndSanitiseSubmission(array $data): array {
        $sanitisedData = [];
        $validationErrors = [];

        $fields = $this->getFields(true)
            ->keyBy(fn(Field $field) => $field->id);

        foreach ($fields as $fieldId => $field) {
            $submitted = $data[$fieldId] ?? [];
            $rawValue = is_array($submitted) && array_key_exists('value', $submitted)
                ? $submitted['value']
                : null;

            $value = $this->sanitiseFieldValue($field, $rawValue);

            $sanitisedData[$fieldId] = [
                'id'    => $field->id,
                'name'  => $field->getName(),
                'label' => $field->getLabel(),
                'value' => $value,
            ];

            $fieldErrors = $this->validateFieldValue($field, $value);

            if (!empty($fieldErrors)) {
                $validationErrors[$fieldId] = $fieldErrors;
            }
        }

        return [$sanitisedData, $validationErrors];
    }

    /**
     * Sanitises a submitted value using field-aware rules.
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    private function sanitiseFieldValue(Field $field, mixed $value): mixed {
        if (is_array($value)) {
            return array_map(fn($item) => $this->sanitiseFieldValue($field, $item), $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($field->handle === 'rich_text') {
            if (function_exists('wp_kses_post')) {
                return wp_kses_post($value);
            }

            return strip_tags($value);
        }

        return trim($value);
    }

    /**
     * Validates a field value against required and min/max style field rules.
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return array<int, string>
     */
    private function validateFieldValue(Field $field, mixed $value): array {
        $errors = [];
        $label = $field->getLabel();
        $rules = $field->getRules();

        if ($field->isRequired() && $this->isEmptySubmissionValue($value)) {
            $errors[] = "{$label} is required.";
        }

        if ($this->isEmptySubmissionValue($value)) {
            return $errors;
        }

        if ($field->handle === 'email') {
            $emailValue = is_string($value) ? trim($value) : '';

            $isValidEmail = function_exists('is_email')
                ? is_email($emailValue) !== false
                : filter_var($emailValue, FILTER_VALIDATE_EMAIL) !== false;

            if (!$isValidEmail) {
                $errors[] = "{$label} must be a valid email address.";
            }
        }

        $minNumber = $rules['min']['value'] ?? null;
        $maxNumber = $rules['max']['value'] ?? null;

        if ($minNumber !== null || $maxNumber !== null) {
            if (!is_numeric($value)) {
                $errors[] = "{$label} must be a valid number.";
            } else {
                $numericValue = (float) $value;

                if ($minNumber !== null && $numericValue < (float) $minNumber) {
                    $errors[] = "{$label} must be at least {$minNumber}.";
                }

                if ($maxNumber !== null && $numericValue > (float) $maxNumber) {
                    $errors[] = "{$label} must not exceed {$maxNumber}.";
                }
            }
        }

        $minChars = $rules['min-chars']['value'] ?? null;
        $maxChars = $rules['max-chars']['value'] ?? null;
        $minWords = $rules['min-words']['value'] ?? null;
        $maxWords = $rules['max-words']['value'] ?? null;

        if ($minChars !== null || $maxChars !== null || $minWords !== null || $maxWords !== null) {
            $stringValue = is_string($value) ? $value : (string) $value;
            $plainText = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($stringValue) : strip_tags($stringValue);
            $charCount = mb_strlen($stringValue);
            $wordCount = str_word_count(trim($plainText));

            if ($minChars !== null && $charCount < (int) $minChars) {
                $errors[] = "{$label} must be at least {$minChars} characters.";
            }

            if ($maxChars !== null && $charCount > (int) $maxChars) {
                $errors[] = "{$label} must not exceed {$maxChars} characters.";
            }

            if ($minWords !== null && $wordCount < (int) $minWords) {
                $errors[] = "{$label} must be at least {$minWords} words.";
            }

            if ($maxWords !== null && $wordCount > (int) $maxWords) {
                $errors[] = "{$label} must not exceed {$maxWords} words.";
            }
        }

        $minItems = $rules['min-items']['value'] ?? null;
        $maxItems = $rules['max-items']['value'] ?? null;

        if ($minItems !== null || $maxItems !== null) {
            if (!is_array($value)) {
                $errors[] = "{$label} must contain valid items.";
            } else {
                $itemCount = count($value);

                if ($minItems !== null && $itemCount < (int) $minItems) {
                    $errors[] = "{$label} must contain at least {$minItems} items.";
                }

                if ($maxItems !== null && $itemCount > (int) $maxItems) {
                    $errors[] = "{$label} must not contain more than {$maxItems} items.";
                }
            }
        }

        return $errors;
    }

    /**
     * Determines whether the given submission value should be treated as empty.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isEmptySubmissionValue(mixed $value): bool {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    /**
     * Clears submitted values after a successful save and resets paged forms to page 1.
     *
     * @return void
     */
    private function resetFormAfterSuccessfulSubmit(): void {
        $clearedState = [];

        $this->getFields(true)->each(function (Field $field) use (&$clearedState) {
            $dataType = $field->getExactDataType();
            $emptyValue = str_starts_with($dataType, 'array') ? [] : null;

            $field->value($emptyValue);

            $clearedState[$field->id] = [
                'id'    => $field->id,
                'name'  => $field->getName(),
                'label' => $field->getLabel(),
                'value' => $emptyValue,
            ];
        });

        $this->fieldState = $clearedState;

        if ($this->isPagedView && $this->totalGroupPages > 0) {
            $this->groupPageDirection = 'backward';
            $this->goToGroupPage(0);
        }
    }

    /**
     * Navigates paged forms to the page containing the first validation error.
     *
     * @param array<string, array<int, string>> $validationErrors
     *
     * @return int|null
     */
    private function navigateToFirstValidationErrorPage(array $validationErrors): ?int {
        if (!$this->isPagedView || $this->totalGroupPages <= 1 || empty($validationErrors)) {
            return null;
        }

        $firstErroredFieldId = array_key_first($validationErrors);

        if (!is_string($firstErroredFieldId) || $firstErroredFieldId === '') {
            return null;
        }

        $fieldPageMap = $this->buildFieldPageMap();

        if (!array_key_exists($firstErroredFieldId, $fieldPageMap)) {
            return null;
        }

        $targetPageIndex = $fieldPageMap[$firstErroredFieldId];
        $this->goToGroupPage($targetPageIndex);

        return $targetPageIndex;
    }

    /**
     * Builds a map of field IDs to their rendered page index in paged mode.
     *
     * @return array<string, int>
     */
    private function buildFieldPageMap(): array {
        $fieldPageMap = [];
        $currentPageIndex = 0;
        $syntheticPageIndex = null;

        foreach ($this->rows as $row) {
            if (!($row instanceof FormRow)) {
                continue;
            }

            if (($row->type ?? null) === 'group') {
                $groupRows = $row->group?->rows ?? [];

                foreach ($groupRows as $groupRow) {
                    foreach ($groupRow->fields ?? [] as $field) {
                        if ($field instanceof Field && !empty($field->id)) {
                            $fieldPageMap[$field->id] = $currentPageIndex;
                        }
                    }
                }

                $currentPageIndex++;
                continue;
            }

            if ($syntheticPageIndex === null) {
                $syntheticPageIndex = $currentPageIndex;
                $currentPageIndex++;
            }

            foreach ($row->fields ?? [] as $field) {
                if ($field instanceof Field && !empty($field->id)) {
                    $fieldPageMap[$field->id] = $syntheticPageIndex;
                }
            }
        }

        return $fieldPageMap;
    }

    /**
     * Persists current field values so they survive paged navigation re-renders.
     *
     * @param array $data
     *
     * @return void
     */
    public function syncFormState(array $data): void {
        $this->fieldState = collect($data)
            ->mapWithKeys(function ($field, $fieldId) {
                if (!is_array($field)) {
                    return [$fieldId => [
                        'id' => (string) $fieldId,
                        'value' => $field,
                    ]];
                }

                return [$fieldId => [
                    'id'    => $field['id'] ?? (string) $fieldId,
                    'name'  => $field['name'] ?? null,
                    'label' => $field['label'] ?? null,
                    'value' => $field['value'] ?? null,
                ]];
            })
            ->toArray();
    }

    // =========================================================================
    // Form Navigation Methods
    // =========================================================================

    /**
     * Navigates to a specific group page in the form.
     *
     * @param int $index The index of the target group page to navigate to.
     *
     * @return void
     */
    public function goToGroupPage(int $index): void {
        if ($this->totalGroupPages <= 0) {
            $this->activeGroupPage = 0;
            $this->groupPageDirection = 'forward';
            return;
        }

        $targetIndex = max(0, min($index, $this->totalGroupPages - 1));

        if ($targetIndex === $this->activeGroupPage) {
            return;
        }

        $this->groupPageDirection = $targetIndex > $this->activeGroupPage ? 'forward' : 'backward';
        $this->activeGroupPage = $targetIndex;
    }

    /**
     * Navigates to the next group page in the form.
     *
     * @return void
     */
    public function nextGroupPage(): void {
        $this->goToGroupPage($this->activeGroupPage + 1);
    }

    /**
     * Navigates to the previous group page in the form.
     *
     * @return void
     */
    public function prevGroupPage(): void {
        $this->goToGroupPage($this->activeGroupPage - 1);
    }

    /**
     * Sets the form to paged view mode and recalculates the group pages.
     *
     * @return void
     */
    public function setPagedView(): void {
        $this->isPagedView = true;
        $this->recalculateGroupPages();
    }

    /**
     * Sets the form to full view mode, disabling pagination.
     *
     * @return void
     */
    public function setFullView(): void {
        $this->isPagedView = false;
    }

    /**
     * Recalculates the total number of group pages in the form based on the current rows.
     * Updates the active group page if it exceeds the new total.
     *
     * @return void
     */
    private function recalculateGroupPages(): void {
        $groupPages = 0;
        $hasUngroupedRows = false;

        foreach ($this->rows as $row) {
            if (($row->type ?? null) === 'group') {
                $groupPages++;
                continue;
            }

            $hasUngroupedRows = true;
        }

        $this->totalGroupPages = $groupPages + ($hasUngroupedRows ? 1 : 0);

        if ($this->totalGroupPages <= 0) {
            $this->activeGroupPage = 0;
            return;
        }

        if ($this->activeGroupPage > $this->totalGroupPages - 1) {
            $this->activeGroupPage = $this->totalGroupPages - 1;
        }

        if ($this->activeGroupPage < 0) {
            $this->activeGroupPage = 0;
        }
    }
}