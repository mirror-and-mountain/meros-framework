<?php 

namespace MM\Meros\App\Fields;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\Services\Contracts\Forms\Field;

use MM\Meros\Facades\Fields as FieldsRegister;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;

use MM\Meros\Facades\Context;

class Repeater extends Field {
    public static string $category = 'specialised';
    public static string $icon = 'table';
    
    /**
     * The root name for the repeater field, used to generate sub-field names.
     *
     * @var string
     */
    protected string $rootName = '';

    /**
     * The placeholder text to show in the repeater when there are no rows.
     *
     * @var string
     */
    protected string $placeholder = 'Nothing to show.';

    /**
     * Whether to allow adding/removing/reordering rows in the repeater. 
     * If null, will default to true.
     *
     * @var boolean|null
     */
    protected ?bool $allowRemove = null;

    /**
     * Whether to allow adding new rows in the repeater. 
     * If null, will default to true.
     *
     * @var boolean|null
     */
    protected ?bool $allowAdd = null;

    /**
     * Whether to allow reordering rows in the repeater. 
     * If null, will default to true.
     *
     * @var boolean|null
     */
    protected ?bool $allowReorder = null;

    /**
     * Whether to allow configuring rows in the repeater. 
     * If null, will default to true.
     *
     * @var boolean|null
     */
    protected ?bool $allowConfigure = null;

    /**
     * The fields that belong to this repeater.
     *
     * @var array<Field|array>
     */
    public array $fields = [];

    /**
     * Custom configuration dialogs for repeater rows, each with an optional rule for when to show the dialog.
     *
     * @var array
     */
    protected array $customConfigurationDialogs = [];

    /**
     * A hidden field used to store the configuration for a repeater row when using a custom configuration dialog.
     *
     * @var Field|null
     */
    protected ?Field $hiddenConfigurationField = null;

    /**
     * The text to show in the button for adding new rows to the repeater.
     *
     * @var string
     */
    protected string $addRowText = '';

    /**
     * The text to show in the button for configuring a row in the repeater.
     *
     * @var string
     */
    protected string $configureRowText = '';

    /**
     * The text to show in the button for removing a row from the repeater.
     *
     * @var string
     */
    protected string $removeRowText = '';

    /**
     * An optional list of field names that must have a non-empty value before the
     * configure button is enabled for a row. Only applies when a custom onConfigureRow
     * callback is set.
     *
     * @var array<string>
     */
    protected array $configureRequiredFields = [];

    // =========================================================================
    // Initialisation
    // =========================================================================

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);

        if (isset($props['attributes']['placeholder'])) {
            $this->placeholder((string) $props['attributes']['placeholder']);
        }

        $this->instantiateFields();
    }

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'repeater';
        $this->compatibleDataTypes = ['array.object'];

        $this->addSupports([
            'required',
            'placeholder',
            'helpText'
        ]);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Renders the field using its designated FieldWrapper.
     * 
     * @param bool  $wrapped Whether to render the field within its wrapper. If false, only the field's input component will be rendered without any wrapper or additional elements.
     * @param array $props   An optional array of properties for rendering the field. This can include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return void
     */
    public function render(bool $wrapped = true, array $props = []): void {
        $props = $this->getRenderProps($props);

        $allowsActions = $props['allowsConfigure'] || $props['allowsRemove'];
        $props['columnCount']   = count($this->fields) + ($props['allowsReorder'] ? 1 : 0) + ($allowsActions ? 1 : 0);

        if ($wrapped) {
            $wrapper = $this->resolveFieldWrapper();

            echo view($wrapper, $props);
        }

        else {
            echo view($parsedConfig['component'] ?? $this->getFieldComponent(), $props);
        }
    }

    /**
     * Retrieves the rendering properties for the field, applying defaults where necessary.
     *
     * @param array $props An array of properties that may include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return array An array containing the parsed properties with defaults applied.
     */
    protected function getRenderProps(array $props = []): array {
        $parsedProps = $this->parseRenderProps($props);

        return [
            'view'                       => $parsedProps['component'] ?? $this->getFieldComponent(),
            'field'                      => $this,
            'id'                         => $parsedProps['id'],
            'name'                       => $parsedProps['name'],
            'label'                      => $parsedProps['label'] ?? $this->getLabel(),
            'helpText'                   => $parsedProps['helpText'] ?? $this->getHelpText(),
            'value'                      => $this->getValue(),
            'columnCount'                => count($this->fields) + ($parsedProps['allowsReorder'] ? 1 : 0) + (($parsedProps['allowsConfigure'] || $parsedProps['allowsRemove']) ? 1 : 0),
            'fieldCount'                 => count($this->fields),
            'rows'                       => $this->buildRows(),
            'templateRow'                => $this->buildTemplateRow(),
            'fieldNames'                 => $this->getFieldNames(),
            'fieldLabels'                => $this->getFieldLabels(),
            'attributes'                 => $this->attributes($parsedProps['attributes'] ?? [], $parsedProps['excludeAttributes'] ?? []),
            'placeholder'                => $parsedProps['placeholder'],
            'allowsAdd'                  => $parsedProps['allowsAdd'],
            'allowsRemove'               => $parsedProps['allowsRemove'],
            'allowsConfigure'            => $parsedProps['allowsConfigure'],
            'showsActionsColumn'         => $parsedProps['allowsConfigure'] || $parsedProps['allowsRemove'],
            'allowsReorder'              => $parsedProps['allowsReorder'],
            'addRowText'                 => $parsedProps['addRowText'],
            'configureRowText'           => $parsedProps['configureRowText'],
            'removeRowText'              => $parsedProps['removeRowText'],
            'hasRules'                   => $this->hasRules(),
            'rules'                      => $parsedProps['rules'],
            'serialisedRules'            => json_encode($parsedProps['rules']),
            'maxRows'                    => $this->getRuleValue('max-items'),
            'minRows'                    => $this->getRuleValue('min-items'),
            'showMinHint'                => $parsedProps['showMinHint'],
            'showMaxHint'                => $parsedProps['showMaxHint'],
            'configureRequiredFields'    => $parsedProps['configureRequiredFields'],
            'customConfigurationDialogs' => $parsedProps['customConfigurationDialogs']
        ];
    }

    /**
     * Parses the rendering properties for the field, applying defaults where necessary.
     *
     * @param array $props An array of properties that may include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return array An array containing the parsed properties with defaults applied.
     */
    protected function parseRenderProps(array $props): array {
        $parsedProps = parent::parseRenderProps($props);

        $parsedProps['placeholder'] = is_string($props['placeholder'] ?? null)
            ? $props['placeholder']
            : $this->placeholder;

        $parsedProps['allowsAdd'] = is_bool($props['allowsAdd'] ?? null)
            ? $props['allowsAdd']
            : ($this->allowAdd ?? true);

        $parsedProps['allowsRemove'] = is_bool($props['allowsRemove'] ?? null)
            ? $props['allowsRemove']
            : ($this->allowRemove ?? true);

        $parsedProps['allowsReorder'] = is_bool($props['allowsReorder'] ?? null)
            ? $props['allowsReorder']
            : ($this->allowReorder ?? true);

        $parsedProps['allowsConfigure'] = is_bool($props['allowsConfigure'] ?? null)
            ? $props['allowsConfigure']
            : ($this->allowConfigure ?? true);

        $parsedProps['addRowText'] = is_string($props['addRowText'] ?? null)
            ? $props['addRowText']
            : ($this->addRowText ?: 'Add Row');

        $parsedProps['configureRowText'] = is_string($props['configureRowText'] ?? null)
            ? $props['configureRowText']
            : ($this->configureRowText ?: 'Open');

        $parsedProps['removeRowText'] = is_string($props['removeRowText'] ?? null)
            ? $props['removeRowText']
            : ($this->removeRowText ?: 'Remove');

        $parsedProps['configureRequiredFields'] = is_array($props['configureRequiredFields'] ?? null)
            ? $props['configureRequiredFields']
            : $this->configureRequiredFields;

        $parsedProps['customConfigurationDialogs'] = is_array($props['customConfigurationDialogs'] ?? null)
            ? $props['customConfigurationDialogs']
            : $this->customConfigurationDialogs;

        return $parsedProps;
    }

    /**
     * Renders the default value control for the repeater field in the field settings panel.
     * Repeaters do not have a default value control, so this method is intentionally left blank.
     *
     * @return void
     */
    public function renderDefaultValueControl(): void {
        return; // Repeaters don't have a default value control in the field settings panel
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.repeater';
    }

    /**
     * Builds row arrays of cloned sub-fields for each repeater item.
     *
     * @return array
     */
    public function buildRows(): array {
        $value = $this->getValue();
        $items = is_array($value) && !empty($value)
            ? $value
            : [];

        $rows = [];

        foreach ($items as $index => $rowData) {
            $rowData  = is_array($rowData) ? $rowData : [];
            $rowToken = $this->resolveRowToken($rowData, $index);
            $rows[]   = $this->buildRowFields($rowData, $rowToken);
        }

        return $rows;
    }

    /**
     * Builds a hidden template row for the empty-state repeater UI.
     *
     * @return array
     */
    public function buildTemplateRow(): array {
        $rowToken = $this->resolveRowToken([], 0);

        return $this->buildRowFields([], $rowToken, true);
    }

    // =========================================================================
    // Configuration Dialogs
    // =========================================================================

    /**
     * Defines a custom configuration dialog for repeater rows with an optional rule for when to show the dialog. 
     * The callback receives a FieldGroup instance to which fields can be added for the dialog. 
     * 
     * The rule is an array in the format [fieldName, operator, value] that determines when to 
     * show the dialog based on the values of the row's fields.
     *
     * @param Closure $callback
     * @param array   $rule
     *
     * @return self
     */
    public function customConfigurationDialog(Closure $callback, array $rule = [], array $default = []): self {
        $dialog = FieldGroupsRegister::checkout($this->provider)->make();

        $callback($dialog);

        $this->customConfigurationDialogs[] = [
            'rule'  => $rule,
            'html'  => $dialog->html(['class' => 'meros-form-group--no-style']),
        ];

        $this->ensureHiddenConfigurationField($default);

        return $this;
    }

    /**
     * Defines a custom configuration dialog from pre-rendered HTML.
     *
     * @param string $html
     * @param array $rule
     * @param array $default
     *
     * @return self
     */
    public function customConfigurationDialogHtml(string $html, array $rule = [], array $default = []): self {
        if (trim($html) === '') {
            return $this;
        }

        $this->customConfigurationDialogs[] = [
            'rule' => $rule,
            'html' => $html,
        ];

        $this->ensureHiddenConfigurationField($default);

        return $this;
    }

    /**
     * Ensures the repeater has a hidden JSON field to store row dialog configuration.
     *
     * @param array $default
     * @return void
     */
    private function ensureHiddenConfigurationField(array $default = []): void {
        if ($this->hiddenConfigurationField !== null) {
            return;
        }

        $this->hiddenConfigurationField = FieldsRegister::checkout($this->provider)
            ->makeFrom('hidden', function (Field $field) use ($default) {
                $field->name('__configuration')
                    ->hideInRepeaterTable()
                    ->attribute('data-repeater-configuration-field', true)
                    ->attribute('value-as-json', 'true')
                    ->default(json_encode($default));
            });

        $this->field($this->hiddenConfigurationField);
    }

    /**
     * Gets the custom configuration dialogs for repeater rows, including their rules and rendered HTML.
     *
     * @return array
     */
    private function getCustomConfigurationDialogs(): array {
        return collect($this->customConfigurationDialogs)
            ->map(fn($dialog) => $dialog ?? [])
            ->toArray();
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets whether the repeater field is required.
     *
     * @param boolean $required
     *
     * @return static
     */
    public function required(bool $required = true): static {
        if ($required) {
            $this->attribute('data-required', 'true');
        } else {
            $this->removeAttribute('data-required');
        }

        return $this;
    }

    /**
     * Sets the placeholder text to show in the repeater when there are no rows.
     *
     * @param string $text
     *
     * @return static
     */
    public function placeholder(string $text): static {
        if (empty($text)) {
            $text = 'Nothing to show.';
        }

        $this->placeholder = $text;
        $this->attribute('placeholder', $text);
        return $this;
    }

    /**
     * Sets whether to allow adding new rows in the repeater.
     *
     * @param boolean $allowAdd
     *
     * @return static
     */
    public function allowAdd(bool $allowAdd = true): static {
        $this->allowAdd = $allowAdd;
        return $this;
    }

    /**
     * Sets whether to allow removing rows in the repeater.
     *
     * @param boolean $allowRemove
     *
     * @return static
     */
    public function allowRemove(bool $allowRemove = true): static {
        $this->allowRemove = $allowRemove;
        return $this;
    }

    /**
     * Sets whether to allow reordering rows in the repeater.
     *
     * @param boolean $allowReorder
     *
     * @return static
     */
    public function allowReorder(bool $allowReorder = true): static {
        $this->allowReorder = $allowReorder;
        return $this;
    }

    /**
     * Sets whether to allow configuring rows in the repeater.
     *
     * @param boolean $allowConfigure
     *
     * @return static
     */
    public function allowConfigure(bool $allowConfigure = true): static {
        $this->allowConfigure = $allowConfigure;
        return $this;
    }

    /**
     * Sets the text to show in the button for adding new rows to the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function addRowText(string $text): static {
        $this->addRowText = $text;
        return $this;
    }

    /**
     * Sets the text to show in the button for configuring a row in the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function configureRowText(string $text): static {
        $this->configureRowText = $text;
        return $this;
    }

    /**
     * Sets the text to show in the button for removing a row from the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function removeRowText(string $text): static {
        $this->removeRowText = $text;
        return $this;
    }

    // =========================================================================
    // Field Setters
    // =========================================================================

    /**
     * Sets the field names that must have a non-empty value before the configure button
     * is enabled on a row. Only applied when a custom onConfigureRow callback is set.
     *
     * @param array<string> $fieldNames
     *
     * @return static
     */
    public function configureRequiredFields(array $fieldNames): static {
        $this->configureRequiredFields = array_values(array_filter(
            array_map('strval', $fieldNames),
            fn($name) => trim($name) !== ''
        ));
        return $this;
    }

    /**
     * Creates a new field instance, adds it to the repeater's fields array, and returns it for chaining.
     *
     * @param Field|string       $field The field handle or instance to add as a sub-field.
     * @param Closure|array|null $callback An optional callback function or array of properties to apply to the field instance after creation.
     * @param array              $props Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     */
    public function field(Field|string $field, Closure|array|null $callback = null, array $props = []): Field {
       $params = func_num_args();
    
        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        if (is_string($field)) {
            $field = FieldsRegister::checkout($this->provider)->makeFrom($field, $callback, $props);
        } else {
            FieldsRegister::checkout($this->provider)->attach($field);
        }

        $position = $props['position'] ?? -1;

        if ($position === -1 || $position >= count($this->fields)) {
            $this->fields[] = $field;
        } else {
            array_splice($this->fields, $position, 0, [$field]);
        }

        $field->repeater($this);

        return $field;
    }

    /**
     * Creates a new field instance, adds it to the repeater's fields array, and returns it for chaining.
     * Alias for field() method.
     *
     * @param string             $fieldIdOrClass The class name of the field to instantiate.
     * @param Closure|array|null $callback An optional callback function or array of properties to apply to the field instance after creation.
     * @param array              $props Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     */
    public function subField(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        return $this->field($fieldIdOrClass, $callback, $props);
    }

    /**
     * Attaches one or more existing field instances to the repeater.
     * Alias for field() when passing existing fields.
     *
     * @param Field|array<Field> $field
     *
     * @return self
     */
    public function attach(Field|array $field): self {
        if (is_array($field)) {
            foreach ($field as $subField) {
                if ($subField instanceof Field) {
                    $this->field($subField);
                }
            }

            return $this;
        }

        $this->field($field);
        return $this;
    }

    /**
     * Refreshes the repeater's fields with a new array of field instances.
     *
     * @param array $fields<Field>
     *
     * @return void
     */
    public function refreshFields(array $fields): void {
        $this->fields = $fields;
    }

    /**
     * Moves a field to a new position within the repeater's fields array.
     *
     * @param string  $fieldId
     * @param integer $newPosition
     *
     * @return void
     */
    public function moveField(string $fieldId, int $newPosition): void {
        $field = collect($this->fields)->firstWhere('id', $fieldId);

        if ($field === null) {
            return; // Field not found in the repeater
        }

        $currentIndex = array_search($field, $this->fields, true);

        if ($currentIndex === false) {
            return; // Field not found in the repeater
        }

        array_splice($this->fields, $currentIndex, 1); // Remove the field from its current position

        if ($newPosition >= count($this->fields)) {
            $this->fields[] = $field; // Add to the end if new position is out of bounds
        } else {
            array_splice($this->fields, $newPosition, 0, [$field]); // Insert at the new position
        }

        $this->fields = array_values($this->fields); // Reindex the array
    }

    /**
     * Removes a field from the repeater's fields array.
     *
     * @param Field $field The field instance to remove.
     *
     * @return self
     */
    public function removeField(Field $field): self {
        $this->fields = array_filter($this->fields, fn($f) => $f !== $field);
        $this->fields = array_values($this->fields); // Reindex the array
        return $this;
    }

    /**
     * Removes a field from the repeater's fields array.
     * Alias for removeField() method.
     *
     * @param Field $field The field instance to remove.
     *
     * @return self
     */
    public function detach(Field $field): self {
        return $this->removeField($field);
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the fields that belong to this repeater as a collection.
     * 
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array 
     */
    public function getFields(bool $asArray = false): Collection|array {
        return $asArray ? $this->fields : collect($this->fields);
    }

    /**
     * Returns the names of all sub-items defined for the repeater field.
     *
     * @return array
     */
    protected function getFieldNames(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getName())
            ->toArray();
    }

    /**
     * Returns the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    protected function getFieldLabels(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getLabel())
            ->toArray();
    }

    // =========================================================================
    // Serialisation
    // =========================================================================

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson();

        $json['fields'] = array_map(function($field) {
            return $field->toJson();
        }, $this->fields);
        
        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Instantiates any sub fields that are provided as an array.
     *
     * @return void
     */
    protected function instantiateFields(): void {
        if (empty($this->fields)) {
            return;
        }

        foreach ($this->fields as $index => $fieldData) {
            if ($fieldData instanceof Field) {
                continue;
            }

            if (!is_array($fieldData) || !isset($fieldData['type'])) {
                continue; // Skip invalid field data
            }

            $field = $fieldData['type']::initFromData($fieldData);

            $field->repeater($this);

            $this->fields[$index] = $field;
        }
    }

    /**
     * Generates a unique name for a sub-field based on the repeater's root name, the repeater's name, the row index, and the sub-field's name.
     *
     * @param Field $field The sub-field for which to generate the name.
     * @param string $rowToken Stable row token.
     *
     * @return string The generated sub-field name.
     */
    protected function generateSubFieldName(Field $field, string $rowToken): string {
        $fieldName = $field->getName();

        if ($this->rootName === '') {
            return "{$this->name}[{$rowToken}][{$fieldName}]";
        }

        return "{$this->rootName}[{$this->name}][{$rowToken}][{$fieldName}]";
    }

    /**
     * Generates a unique ID for a sub-field based on the repeater's root name, the repeater's ID, the row index, and the sub-field's ID.
     *
     * @param Field $field The sub-field for which to generate the ID.
     * @param string $rowToken Stable row token.
     *
     * @return string The generated sub-field ID.
     */
    protected function generateSubFieldId(Field $field, string $rowToken): string {
        $fieldId = Str::replace(['[', ']'], '_', $field->getId());
        $idToken = preg_replace('/[^A-Za-z0-9_-]/', '_', $rowToken) ?? $rowToken;

        if ($this->rootName === '') {
            return "{$this->id}_{$idToken}_{$fieldId}";
        }

        return "{$this->rootName}_{$this->id}_{$idToken}_{$fieldId}";
    }

    /**
     * Builds a row of cloned repeater sub-fields with resolved ids, names, and row metadata.
     *
     * @param array  $rowData Row values keyed by base field name.
     * @param string $rowToken Stable row token.
     * @param bool   $isTemplate Whether the row is the hidden template row.
     *
     * @return array
     */
    protected function buildRowFields(array $rowData, string $rowToken, bool $isTemplate = false): array {
        $row = [];

        foreach ($this->fields as $field) {
            $fieldInstance = clone $field;

            // Store the original field name before generating the indexed name.
            $baseFieldName = $fieldInstance->getName();
            $fieldId = $this->generateSubFieldId($fieldInstance, $rowToken);

            if ($isTemplate) {
                $fieldId .= '-template';
            }

            $fieldInstance->attribute('data-row-index', $rowToken);
            $fieldInstance->attribute('data-base-field-name', $baseFieldName);
            $fieldInstance->attribute('data-repeater-preserve-disabled', $field->isDisabled() ? 'true' : 'false');
            $fieldInstance->id($fieldId);
            $fieldInstance->name($this->generateSubFieldName($fieldInstance, $rowToken));

            if ($isTemplate) {
                $fieldInstance->attribute('disabled', true);
                $fieldInstance->attribute('data-disabled-for-template-only', $fieldInstance->isDisabled() ? 'true' : 'false');
            } else {
                // Look up value using the base field name, not the generated indexed name.
                $fieldInstance->value($rowData[$baseFieldName] ?? null);
            }

            // Key the row by base field name so repeater view can access with getFieldNames().
            $row[$baseFieldName] = $fieldInstance;
        }

        return $row;
    }

    /**
     * Resolve the repeater row token used for generated sub-field names/ids.
     * Uses a stable row key in admin contexts to avoid radio group churn when reordering.
     */
    protected function resolveRowToken(array $rowData, int $index): string {
        if (Context::isAdmin()) {
            $rowKey = $rowData['__rowKey'] ?? null;

            if (is_string($rowKey) && $rowKey !== '') {
                return $rowKey;
            }
        }

        return (string) $index;
    }
}