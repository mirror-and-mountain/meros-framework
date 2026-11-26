<?php

namespace MM\Meros\Contracts\Features\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Contracts\Features\Components\Concerns\IsFormComponent;
use MM\Meros\Contracts\Features\Components\Concerns\MakesFieldRows;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Support\Ajax;
use MM\Meros\Facades\Components\Fields;
use MM\Meros\Support\FormConditionSources;
use MM\Meros\App\Components\Fields\Repeater;

class Form extends Feature implements FormComponent, Makeable {
    /**
     * The form's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The form's name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The form's title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The form's description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The view used to render the form.
     *
     * @var string
     */
    private string $view = 'meros::forms.form';

    /**
     * The message displayed upon successful form submission.
     *
     * @var string
     */
    protected string $successMessage = 'Form Submitted Successfully.';

    /**
     * The text shown on the form's submit button.
     *
     * @var string
     */
    protected string $submitText = 'Submit';

    /**
     * Text that can be shown when the form is submitted but is invalid.
     *
     * @var string
     */
    protected string $invalidText = "The form is invalid. Please check the information you've entered.";

    /**
     * Override styles applied to the form.
     *
     * @var array
     */
    protected array $styles = [];

    /**
     * Whether to use the default styles for the form.
     *
     * @var boolean
     */
    protected bool $useDefaultStyles = false;

    /**
     * The js callback function to be executed when the form is submitted.
     *
     * @var string|null
     */
    protected string|null $onSubmit = null;

    /**
     * Whether to hide the form's title.
     *
     * @var boolean
     */
    protected bool $hideTitle = false;

    /**
     * Whether to hide the form's description.
     *
     * @var boolean
     */
    protected bool $hideDescription = false;

    /**
     * Whether to hide the submit button for the form.
     *
     * @var bool
     */
    protected bool $hideSubmitButton = false;

    /**
     * Whether to display the form as paginated pages.
     *
     * @var boolean
     */
    protected bool $displayAsPages = false;

    /**
     * Whether to show the success message on a new page.
     *
     * @var boolean
     */
    protected bool $showSuccessOnNewPage = false;

    /**
     * Whether to show the option to submit another response.
     *
     * @var boolean
     */
    protected bool $showSubmitAnotherResponse = false;

    /**
     * Whether to resolve dynamic values for the form.
     *
     * @var boolean
     */
    protected bool $resolveDynamicValues = true;

    /**
     * WP's ajax url.
     *
     * @var string|null
     */
    private ?string $ajaxUrl = null;

    /**
     * The ajax nonce used for submitting the form.
     *
     * @var string|null
     */
    private ?string $ajaxNonce = null;

    use IsFormComponent,
        IsMakeable,
        MakesFieldRows,
        InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('id', 'slug');

        $this->setSerializableProperties(([
            'id',
            'name',
            'title',
            'description',
            'attributeString',
            'rows',
            'ajaxUrl',
            'ajaxNonce',
            'successMessage',
            'submitText',
            'invalidText',
            'onSubmit',
            'hideTitle',
            'hideDescription',
            'hideSubmitButton',
            'displayAsPages',
            'showSuccessOnNewPage',
            'showSubmitAnotherResponse',
            'resolveDynamicValues',
            'pages',
        ]));

        $this->setNonPersistableProperties([
            'ajaxUrl',
            'ajaxNonce',
            'resolveDynamicValues',
            'pages',
        ]);

        // Need to update this bit...
        $defaultIdentifier = 'mforms-' . Str::substr(Str::uuid(), 0, 8);
        $this->id($defaultIdentifier);
        $this->name(Str::replace('-', '_', $defaultIdentifier));
    }

    final protected function whenConfigured(): void {
        if (!empty($this->rows)) {
            $this->instantiateRows();
        } else {
            // Create an initial row if none are provided
            $this->makeNewRow();
        }

        if (is_string($this->onSubmit)) {
            return;
        }

        $this->initFormAjax();
    }

    // =========================================================================
    // Submission Handling
    // =========================================================================

    private function initFormAjax(): void {
        $this->ajaxUrl = Ajax::getUrl();
        $this->ajaxNonce = Ajax::getActionNonce('meros_submit_form');
    }

    public function getAjaxUrl(): ?string {
        return $this->ajaxUrl;
    }

    public function getAjaxNonce(): ?string {
        return $this->ajaxNonce;
    }

    /**
     * Creates a payload array using submitted form data for passing to form actions.
     *
     * @param array $fieldValues
     * @param array $hiddenFields Field names reported hidden by the browser.
     *
     * @return array
     */
    public function buildSubmissionPayload(array $fieldValues, array $hiddenFields = []): array {
        $payload = [
            'form_id'         => $this->getId(),
            'form_title'      => $this->getTitle(),
            'submission_time' => now(),
            'user_id'         => get_current_user_id(),
            'data'            => []
        ];

        if ($fieldValues === []) {
            return $payload;
        }

        $fields = $this->getFields(true);

        foreach ($fieldValues as $name => $value) {
            $payload['data'][$name] = [
                'field_name'           => $name,
                'user_value'           => $value,
                'user_value_data_type' => gettype($value),
                'submission_state'     => in_array((string) $name, $hiddenFields, true) ? 'hidden' : 'shown'
            ];

            $field = $fields->firstWhere(function (Field $field) use($name) {
                return $field->getName() === $name;
            });

            if (!($field instanceof Field)) {
                continue;
            }

            $group    = $field->getGroup();
            $row      = $field->getRow();
            $rowIndex = null;

            if ($row instanceof FieldRow) {
                $rowIndex = $group instanceof FieldGroup
                    ? $group->getRowIndex($row)
                    : $this->getRowIndex($row);
            }

            $payload['data'][$name] = array_merge($payload['data'][$name], [
                'field_type'      => $field->getType(),
                'field_data_type' => $field->getDataType(),
                'field_metadata'  => $field->getMetadata(),
                'field_group'     => $group !== null ? $group->getTitle() : null,
                'group_index'     => $group !== null ? $this->getGroupIndex($group) : null,
                'row_index'       => $rowIndex,
                'field_index'     => $field->getRowPosition()
            ]);
        }

        return $payload;
    }

    // =========================================================================
    // Row, Field & Group Management
    // =========================================================================

    /**
     * Adds a field to the FieldGroup instance. If the last row has reached its field capacity, a new FieldRow instance is created for the field.
     *
     * @param string        $type The type of the field to add.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     * @param bool          $autoRow Whether to automatically add the field to an existing row if it has capacity (true) or always create a new row for the field (false). Defaults to true.
     *
     * @return static Returns the current instance for method chaining.
     * @throws \RuntimeException If the field could not be created.
     */
    final public function field(string $type, Closure|array $callbackOrProps = [], bool $autoRow = true): static {
        $fieldClass = Fields::getRegisteredFeatureClass($type);
        
        if ($fieldClass === null) {
            throw new \RuntimeException("Field type '{$type}' is not registered.");
        }

        $lastRow = $this->getLastRow(true);

        if ($autoRow) {
            $fieldWidth     = $fieldClass::getRowPositions();
            $rowHasCapacity = $lastRow->hasCapacityFor($fieldWidth);

            if ($rowHasCapacity) {
                $lastRow->field($type, $callbackOrProps);
                return $this;
            }
        }

        if ($lastRow->isEmpty()) {
            $lastRow->field($type, $callbackOrProps);
            return $this;
        }

        return $this->row(function ($row) use ($type, $callbackOrProps) {
            $row->field($type, $callbackOrProps);
        });
    }

    /**
     * Adds a FieldGroup to the form. If the last row has reached its field capacity, a new FieldRow instance is created for the group.
     *
     * @param FieldGroup|Closure|string|array $section         The FieldGroup to add, which can be a FieldGroup instance, an array of properties, an existing group id, or a closure that configures the group.
     * @param Closure|array                   $callbackOrProps An optional callback to configure the group or an array of properties to pass to the group's constructor.
     *
     * @return static Returns the current instance for method chaining.
     */
    final public function section(FieldGroup|Closure|string|array $section, Closure|array $callbackOrProps = []): static {
        $row = $this->getLastRow(true);
        $rowHasCapacity = $row->hasCapacityFor(3);

        if (!$rowHasCapacity) {
            $row = $this->makeNewRow();
        }
        
        $row->group($section, $callbackOrProps);
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the form's id.
     *
     * @param string $id
     *
     * @return static
     */
    final public function id(string $id): static {
        $id = $this->setIdentifier($id);

        if (empty($this->name) || Str::startsWith($this->name, 'mforms_')) {
            $this->name = Str::replace('-', '_', $id);   
        }

        return $this;
    }

    /**
     * Sets the form's name.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $nameParts = explode('[', $name, 2);
        $this->name = Str::snake($nameParts[0]);

        if (isset($nameParts[1])) {
            $this->name .= '[' . $nameParts[1];
        }

        return $this;
    }

    /**
     * Sets the form's title.
     *
     * @param string $title
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets whether to hide the form's title.
     *
     * @param boolean $hide
     *
     * @return static
     */
    final public function hideTitle(bool $hide = true): static {
        $this->hideTitle = $hide;
        return $this;
    }

    /**
     * Sets whether to hide the form's description.
     *
     * @param boolean $hide
     *
     * @return static
     */
    final public function hideDescription(bool $hide = true): static {
        $this->hideDescription = $hide;
        return $this;
    }

    /**
     * Sets the client-side event to trigger on form submission.
     * 
     * Event names are prefixed with 'mforms::' to avoid conflicts with other events. Example:
     * mforms::myCustomEvent.
     * The event includes the Alpine form instance as detail.form. Callbacks own
     * completion and may call showSuccess() or showError() to display feedback.
     *
     * @param string $jsCallback The name of the client-side event to trigger on form submission.
     *
     * @return static
     */
    final public function onSubmit(string $jsCallback): static {
        $this->onSubmit = $jsCallback;
        return $this;
    }

    /**
     * Sets whether to hide the submit button for the form.
     *
     * @param bool $hide Whether to hide the submit button. Defaults to true.
     *
     * @return static
     */
    final public function hideSubmitButton(bool $hide = true): static {
        $this->hideSubmitButton = $hide;
        return $this;
    }

    /**
     * Sets whether the form should be displayed as pages.
     *
     * @param boolean $display
     *
     * @return static
     */
    final public function displayAsPages(bool $display = true): static {
        $this->displayAsPages = $display;
        return $this;
    }

    /**
     * Sets whether to show the success message on a new page.
     *
     * @param boolean $show
     *
     * @return static
     */
    final public function showSuccessOnNewPage(bool $show = true): static {
        $this->showSuccessOnNewPage = $show;
        return $this;
    }

    /**
     * Sets whether to show the option to submit another response.
     *
     * @param boolean $show
     *
     * @return static
     */
    final public function showSubmitAnotherResponse(bool $show = true): static {
        $this->showSubmitAnotherResponse = $show;
        return $this;
    }

    /**
     * Sets the styles applied to the form.
     *
     * @param array $styles
     *
     * @return static
     */
    final public function styles(array $styles): static {
        $this->styles = $styles;
        return $this;
    }

    /**
     * Enables the use of default styles for the form.
     *
     * @return static
     */
    final public function useDefaultStyles(): static {
        $this->useDefaultStyles = true;
        return $this;
    }

    /**
     * Controls whether field views resolve dynamic defaults, including nested fields.
     * Disable when editing literal default tokens in the form builder.
     * 
     * @param bool $resolve Whether to resolve dynamic values. Defaults to true.
     *
     * @return static
     */
    final public function resolveDynamicValues(bool $resolve = true): static {
        $this->resolveDynamicValues = $resolve;
        return $this;
    }

    /**
     * Sets the text shown on the form's submit button.
     *
     * @param string $text
     *
     * @return static
     */
    final public function submitText(string $text): static {
        $this->submitText = $text;
        return $this;
    }

    /**
     * Sets the message displayed upon successful form submission.
     *
     * @param string $message
     *
     * @return static
     */
    final public function successMessage(string $message): static {
        $this->successMessage = wp_kses_post($message);
        return $this;
    }

    /**
     * Sets the text shown when the form's inputs are invalid.
     *
     * @param string $text
     *
     * @return static
     */
    final public function invalidText(string $text): static {
        $this->invalidText = $text;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the form's id.
     * 
     * @param string $format The format of the identifier to return. Defaults to 'default'.
     *
     * @return string
     */
    final public function getId(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Returns the form's name.
     *
     * @return string
     */
    final public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the form's title.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    final public function getSuccessMessage(): string {
        return $this->successMessage;
    }

    /**
     * Returns the form's rows.
     *
     * @return array
     */
    final public function getRows(): array {
        return $this->rows;
    }

    final public function getPages(): array {
        if (!$this->displayAsPages) {
            return [];
        }

        $pages = [];
        $ungrouped = false;
        foreach ($this->rows as $row) {
            if ($row->isEmpty()) {
                continue;
            }
            $group = $row->getGroup();
            if ($group !== null || !$ungrouped) {
                $pages[] = [
                    'title' => $group !== null && $group->getTitle() !== '' ? $group->getTitle() : 'Page ' . (count($pages) + 1),
                    'rows' => [],
                ];
            }
            $pages[array_key_last($pages)]['rows'][] = $row;
            $ungrouped = $group === null;
        }

        return $pages;
    }

    /**
     * Returns the index of the given FieldRow instance within the form's rows.
     *
     * @param FieldRow $row The FieldRow instance to find the index of.
     * @return int|null The index of the FieldRow instance, or null if not found.
     */
    final public function getRowIndex(FieldRow $row): ?int {
        $index = array_search($row, $this->rows, true);
        return $index !== false ? $index : null;
    }

    /**
     * Returns the index of the given FieldGroup instance within the form's rows.
     *
     * @param FieldGroup $group
     *
     * @return integer|null
     */
    final public function getGroupIndex(FieldGroup $group): ?int {
        $groupRow = collect($this->rows)->firstWhere(function (FieldRow $row) use($group) {
            return $row->getGroup() === $group;
        });

        if ($groupRow instanceof FieldRow) {
            return $this->getRowIndex($groupRow);
        }

        return null;
    }

    /**
     * Returns the onSubmit callback if it is a string (JavaScript function name), or null if it is a Closure.
     *
     * @return string|null
     */
    final public function getOnSubmit(): string|null {
        if ($this->onSubmit instanceof Closure) {
            return null;
        }

        return !empty($this->onSubmit) ? $this->onSubmit : null;
    }

    /**
     * Returns all fields from the component's rows.
     * 
     * @param boolean $collect Whether to return the fields as a Collection. If false, returns as an array.
     *
     * @return array|Collection
     */
    final public function getFields(bool $collect = false): array|Collection {
        $fields = [];

        foreach ($this->rows as $row) {
            if ($row instanceof FieldRow) {
                $fields = array_merge($fields, $row->getFields(true)->all());
            }
        }

        return $collect ? collect($fields) : $fields;
    }

    /**
     * Sets the event name for the repeater edit form loader.
     *
     * @param string $event
     *
     * @return static
     */
    final public function repeaterEditFormLoader(string $event): static {
        $this->attribute('data-repeater-edit-form-loader', $event);
        return $this;
    }

    /**
     * Renders the edit form for a repeater field.
     *
     * @param string $name The name of the repeater field.
     * @param string $originalName The original name of the repeater field.
     * @param array  $rowData The data for the current row.
     * @param array  $currentValue The current value of the repeater field.
     *
     * @return string
     */
    final public function renderRepeaterEditForm(
        string $name,
        string $originalName,
        array $rowData = [],
        array $currentValue = []
    ): string {
        if ($name === '') {
            throw new \InvalidArgumentException('The repeater name is required.');
        }
        $repeater = null;
        foreach (array_unique(array_filter([$name, $originalName])) as $candidate) {
            $repeater = $this->getFields(true)->first(fn ($field) =>
                $field instanceof Repeater && $field->getName() === $candidate
            );
            if ($repeater instanceof Repeater) {
                break;
            }
        }

        if (!($repeater instanceof Repeater) || !$repeater->hasEditForm()) {
            throw new \InvalidArgumentException('Unable to get repeater edit form.');
        }
        if (isset($rowData['form_data']) && !is_array($rowData['form_data'])) {
            throw new \InvalidArgumentException('Repeater form data must be an array.');
        }

        $repeater = clone $repeater;
        $repeater->name($name);
        $form = $repeater->prepareEditForm($rowData, $currentValue);
        if (!($form instanceof self)) {
            throw new \RuntimeException('The repeater edit-form callback must return a Form instance.');
        }

        return $form->html();
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function render(array $properties = [], bool $mergeProperties = false): void {
        $view = $this->view;

        if ($mergeProperties) {
            $properties = array_merge(
                $this->filterSerializedProperties($this->toArray()),
                $properties
            );
        } 
        
        else {
            $properties = empty($properties) 
                ? $this->filterSerializedProperties($this->toArray())
                : $properties;
        }

        if ($this->useDefaultStyles === false) {
            $properties['style'] = $this->mapFormStyles();
        }

        $properties['conditionValues'] = FormConditionSources::values();

        echo view($view, $properties);
    }

    /**
     * Maps the form styles based on the global colour settings or the styles array if it is set.
     *
     * @return string
     */
    private function mapFormStyles(): string {
        $globalColourSettings = empty($this->styles) ? Framework::__getFormsSettings() : $this->styles;
        $styles = '';

        // These styles map to nice-forms.css styles.
        foreach ($globalColourSettings as $key => $value) {
            if (Str::startsWith($key, 'mforms_field')) {
                switch ($key) {
                    case 'mforms_field_bg_color':
                        $formattedKey = '--mforms-nf-input-background-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_text_color':
                        $formattedKey = '--mforms-nf-input-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_border_color':
                        $formattedKey = '--mforms-nf-input-border-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_focus_border_color':
                        $formattedKey = '--mforms-nf-input-focus-border-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_invalid_border_color':
                        $formattedKey = '--mforms-nf-invalid-input-border-bottom-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_valid_border_color':
                        $formattedKey = '--mforms-nf-valid-input-border-bottom-color';
                        $styles .= $formattedKey . ': ' . $value . '; ';
                        break;
                    case 'mforms_field_border_radius':
                        $formattedKey = '--mforms-nf-input-border-radius';
                        $styles .= $formattedKey . ': ' . $value . 'px; ';
                        break;
                }

                continue;
            }
        }

        foreach ($globalColourSettings as $key => $value) {
            if (!Str::startsWith($key, 'mforms')) {
                continue;
            }

            if (Str::startsWith($key, 'mforms_field')) {
                continue;
            }
            
            $formattedStyleKey = '--' . Str::replace('_', '-', $key);
            if (Str::endsWith($key, 'radius')) {
                $value .= 'px';
            }
            $styles .= $formattedStyleKey . ': ' . $value . '; ';
        }

        return $styles;
    }

    public function html(array $properties = [], bool $mergeProperties = false): string {
        ob_start();
        $this->render($properties, $mergeProperties);

        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }
}