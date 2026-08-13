<?php 

namespace MM\Meros\App\Fields;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Facades\Fields as FieldsRegister;

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
     * The fields that belong to this repeater and are shown in the repeater's table view.
     *
     * @var array<Field|array>
     */
    public array $rowFields = [];

    /**
     * Fields associated with this repeater that are only rendered in the repeater's configuration dialog.
     *
     * @var array<Field|array>
     */
    public array $formFields = [];

    /**
     * Indicates that the repeater has a hidden form data field.
     *
     * @var bool
     */
    protected bool $hasHiddenFormDataField = false;

    /**
     * Indicates that the repeater has a hidden nonce field for row configuration.
     *
     * @var bool
     */
    protected bool $hasHiddenNonceField = false;

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
     * A JavaScript callback function to call when a new row is added to the repeater.
     *
     * @var string
     */
    protected string $onAddRow = '';

    /**
     * A JavaScript callback function to call when a row is removed from the repeater.
     *
     * @var string
     */
    protected string $onRemoveRow = '';

    /**
     * A callback to call when the configure button is clicked for a row.
     * If a Closure is provided, it will be executed server-side via an Ajax request. 
     * If a string is provided, it will be treated as a JavaScript function name and called client-side.
     *
     * @var Closure|string
     */
    protected Closure|string $onConfigureRow = 'ajax';

    /**
     * The URL to send Ajax requests to when the configure button is clicked for a row.
     *
     * @var string
     */
    protected string $ajaxUrl = '';

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
        $this->ajaxUrl = admin_url('admin-ajax.php');

        parent::__construct($provider, $props);

        if (isset($props['attributes']['placeholder'])) {
            $this->placeholder((string) $props['attributes']['placeholder']);
        }
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
        $this->ensureNonceField();
        $props = $this->getRenderProps($props);

        if ($wrapped) {
            $wrapper = $this->resolveFieldWrapper();
            echo view($wrapper, $props);
        }

        else {
            echo view($props['component'] ?? $this->getFieldComponent(), $props);
        }
    }

    /**
     * Public alias for getRenderProps(). Retrieves the rendering properties for the field, applying defaults where necessary.
     *
     * @param array $props An array of properties that may include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return array An array containing the parsed properties with defaults applied.
     */
    public function getRefreshedRenderProps(array $props = []): array {
        return $this->getRenderProps($props);
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
            'view'                          => $parsedProps['component'] ?? $this->getFieldComponent(),
            'field'                         => $this,
            'id'                            => $parsedProps['id'],
            'name'                          => $parsedProps['name'],
            'label'                         => $parsedProps['label'] ?? $this->getLabel(),
            'helpText'                      => $parsedProps['helpText'] ?? $this->getHelpText(),
            'value'                         => $this->getValue(),
            'columnCount'                   => count($this->rowFields) + ($parsedProps['allowsReorder'] ? 1 : 0) + (($parsedProps['allowsConfigure'] || $parsedProps['allowsRemove']) ? 1 : 0),
            'fieldCount'                    => count($this->rowFields),
            'rows'                          => $this->buildRows(),
            'templateRow'                   => $this->buildTemplateRow(),
            'fieldNames'                    => $this->getRowFieldNames(),
            'fieldLabels'                   => $this->getRowFieldLabels(),
            'attributes'                    => $this->attributes($parsedProps['attributes'] ?? [], $parsedProps['excludeAttributes'] ?? []),
            'placeholder'                   => $parsedProps['placeholder'],
            'allowsAdd'                     => $parsedProps['allowsAdd'],
            'allowsRemove'                  => $parsedProps['allowsRemove'],
            'allowsConfigure'               => $parsedProps['allowsConfigure'],
            'showsActionsColumn'            => $parsedProps['allowsConfigure'] || $parsedProps['allowsRemove'],
            'allowsReorder'                 => $parsedProps['allowsReorder'],
            'addRowText'                    => $parsedProps['addRowText'],
            'configureRowText'              => $parsedProps['configureRowText'],
            'removeRowText'                 => $parsedProps['removeRowText'],
            'onAddRow'                      => $parsedProps['onAddRow'],
            'onRemoveRow'                   => $parsedProps['onRemoveRow'],
            'onConfigureRow'                => $parsedProps['onConfigureRow'],
            'hasRules'                      => $this->hasRules(),
            'rules'                         => $parsedProps['rules'],
            'serialisedRules'               => json_encode($parsedProps['rules']),
            'maxRows'                       => $this->getRuleValue('max-items'),
            'minRows'                       => $this->getRuleValue('min-items'),
            'showMinHint'                   => $parsedProps['showMinHint'],
            'showMaxHint'                   => $parsedProps['showMaxHint'],
            'configureRequiredFields'       => $parsedProps['configureRequiredFields'],
            'ajaxUrl'                       => $this->ajaxUrl,
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

        $parsedProps['onAddRow'] = is_string($props['onAddRow'] ?? null)
            ? $props['onAddRow']
            : $this->onAddRow;

        $parsedProps['onRemoveRow'] = is_string($props['onRemoveRow'] ?? null)
            ? $props['onRemoveRow']
            : $this->onRemoveRow;

        $parsedProps['onConfigureRow'] = is_string($props['onConfigureRow'] ?? null)
            ? $props['onConfigureRow']
            : $this->getConfigureRowCallback();

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
        return 'meros::forms.fields.repeater-lw';
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

    /**
     * Builds the configuration form fields html for the repeater's configuration dialog.
     *
     * @return string
     */
    public function buildConfigurationFormFields(array $rowData, string $rowToken): string {
        $fields = array_merge(
            collect($this->rowFields)->where('isShownInRepeaterForm', true)->toArray(),
            $this->formFields
        );

        $html = '';

        foreach ($fields as $field) {
            if ($field instanceof Field) {
                $html .= $field->html();
            }
        }

        return $html;
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

        if ($allowConfigure) {
            $this->ensureNonceField();
            $this->ensureFormDataField();
        } else {
            $this->detach(collect($this->rowFields)->where('name', '__row_nonce')->first());
            $this->detach(collect($this->rowFields)->where('name', '__row_data')->first());

            foreach ($this->formFields as $formField) {
                if ($formField instanceof Field) {
                    $this->detach($formField);
                }
            }

            $this->hasHiddenNonceField = false;
            $this->hasHiddenFormDataField = false;
        }

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

    /**
     * Sets a JavaScript callback function to call when a new row is added to the repeater.
     *
     * @param string $jsCallback
     *
     * @return static
     */
    public function onAddRow(string $jsCallback): static {
        $this->onAddRow = $jsCallback;
        return $this;
    }

    /**
     * Sets a JavaScript callback function to call when a row is removed from the repeater.
     *
     * @param string $jsCallback
     *
     * @return static
     */
    public function onRemoveRow(string $jsCallback): static {
        $this->onRemoveRow = $jsCallback;
        return $this;
    }

    /**
     * Sets a callback function to call when the configure button is clicked for a row.
     *
     * @param Closure|string $callback
     *
     * @return static
     */
    public function onConfigureRow(Closure|string $callback): static {
        $this->onConfigureRow = $callback;

        return $this->allowConfigure(true);
    }

    /**
     * Ensures that the hidden nonce field is created and attached to the repeater's fields if it doesn't already exist.
     *
     * @return void
     */
    private function ensureNonceField(): void {
        if ($this->hasHiddenNonceField === true) {
            return;
        }

        $this->rowField('hidden', function ($field) {
            $field->name('__row_nonce');
            $field->hideInRepeaterTable();
            $field->hideInRepeaterForm();
            $field->default(wp_create_nonce('meros_repeater_row_action_' . $this->getName()));
        });

        $this->hasHiddenNonceField = true;
    }

    /**
     * Ensures that the hidden form data field is created and attached to the repeater's fields if it doesn't already exist.
     *
     * @return void
     */
    private function ensureFormDataField(): void {
        if ($this->hasHiddenFormDataField === true) {
            return;
        }

        $this->rowField('hidden', function ($field) {
            $field->name('__row_data');
            $field->attribute('data-has-json', 'true');
            $field->hideInRepeaterTable();
            $field->hideInRepeaterForm();
        });

        $this->hasHiddenFormDataField = true;
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
     * @param Field|string       $field The field handle, class name, or existing Field instance to add as a sub-field.
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
        };

        $valid = (is_string($field) && $field !== 'repeater') || ($field instanceof Repeater === false);

        if (!$valid) {
            throw new \InvalidArgumentException('Invalid field type. Only string handles or Field instances are allowed, and Repeater fields cannot be nested.');
        }

        $field    = FieldsRegister::checkout($this->provider)->makeFrom($field, $callback, $props);
        $position = $props['position'] ?? -1;

        if ($position === -1 || $position >= count($this->rowFields)) {
            $this->rowFields[] = $field;
        } else {
            array_splice($this->rowFields, $position, 0, [$field]);
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
     * Creates a new field instance, adds it to the repeater's fields array, and returns it for chaining.
     * Alias for field() method.
     *
     * @param string             $fieldIdOrClass The class name of the field to instantiate.
     * @param Closure|array|null $callback An optional callback function or array of properties to apply to the field instance after creation.
     * @param array              $props Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     */
    public function rowField(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        return $this->field($fieldIdOrClass, $callback, $props);
    }

    /**
     * Creates a new field instance, adds it to the repeater's form-only fields array, and returns it for chaining.
     *
     * @param string             $fieldIdOrClass The field handle or class name of the field to instantiate.
     * @param Closure|array|null $callback       An optional callback function or array of properties to apply to the field instance after creation.
     * @param array              $props          Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     */
    public function formField(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        $params = func_num_args();
    
        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $field    = FieldsRegister::checkout($this->provider)->makeFrom($fieldIdOrClass, $callback, $props);
        $position = $props['position'] ?? -1;

        if ($position === -1 || $position >= count($this->formFields)) {
            $this->formFields[] = $field;
        } else {
            array_splice($this->formFields, $position, 0, [$field]);
        }

        $field->repeater($this);

        $this->rowFields = array_filter($this->rowFields, fn($f) => $f !== $field);
        $this->allowConfigure(true); // Ensure that the repeater allows configuration if form fields are added

        if ($fieldIdOrClass === 'repeater') {
            $field->allowConfigure(false); // Prevent nested repeaters from allowing configuration
        }

        return $field;
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
     * Moves a field to a new position within the repeater's fields array.
     *
     * @param string  $fieldId
     * @param integer $newPosition
     *
     * @return void
     */
    public function moveField(string $fieldId, int $newPosition): void {
        $field = collect($this->rowFields)->firstWhere('id', $fieldId);

        if ($field === null) {
            return; // Field not found in the repeater
        }

        $currentIndex = array_search($field, $this->rowFields, true);

        if ($currentIndex === false) {
            return; // Field not found in the repeater
        }

        array_splice($this->rowFields, $currentIndex, 1); // Remove the field from its current position

        if ($newPosition >= count($this->rowFields)) {
            $this->rowFields[] = $field; // Add to the end if new position is out of bounds
        } else {
            array_splice($this->rowFields, $newPosition, 0, [$field]); // Insert at the new position
        }

        $this->rowFields = array_values($this->rowFields); // Reindex the array
    }

    /**
     * Removes a field from the repeater's fields array.
     *
     * @param Field $field The field instance to remove.
     *
     * @return self
     */
    public function removeField(Field $field): self {
        $field->repeater(null, null); // Detach the field from the repeater

        $this->rowFields = array_filter($this->rowFields, fn($f) => $f !== $field);
        $this->rowFields = array_values($this->rowFields); // Reindex the array

        $this->formFields = array_filter($this->formFields, fn($f) => $f !== $field);
        $this->formFields = array_values($this->formFields); // Reindex the array
        return $this;
    }

    /**
     * Removes a field from the repeater's fields array.
     * Alias for removeField() method.
     *
     * @param Field|null $field The field instance to remove.
     *
     * @return self
     */
    public function detach(?Field $field): self {
        return $field ? $this->removeField($field) : $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns all fields that belong to this repeater as a collection.
     * 
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array 
     */
    public function getFields(bool $asArray = false): Collection|array {
        $fields = array_merge($this->rowFields, $this->formFields);
        return $asArray ? $fields : collect($fields);
    }

    /**
     * Returns the fields that belong to this repeater as a collection.
     * 
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array 
     */
    public function getRowFields(bool $asArray = false): Collection|array {
        return $asArray ? $this->rowFields : collect($this->rowFields);
    }

    /**
     * Returns the fields that belong to this repeater's configuration form as a collection.
     * 
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array 
     */
    public function getFormFields(bool $asArray = false): Collection|array {
        $rowFieldsInForm = collect($this->rowFields)->where('showInRepeaterForm', true)->toArray();
        $formOnlyFields = $this->formFields;

        $allFormFields = array_merge($rowFieldsInForm, $formOnlyFields);
        return $asArray ? $allFormFields : collect($allFormFields);
    }

    /**
     * Returns the names of all sub-items defined for the repeater field.
     *
     * @return array
     */
    protected function getRowFieldNames(): array {
        return collect($this->rowFields)
            ->map(fn($field) => $field->getName())
            ->toArray();
    }

    /**
     * Returns the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    protected function getRowFieldLabels(): array {
        return collect($this->rowFields)
            ->map(fn($field) => $field->getLabel())
            ->toArray();
    }

    /**
     * Returns the JavaScript callback function to call when a new row is added to the repeater.
     *
     * @return string|null
     */
    public function getAddRowCallback(): string {
        return $this->onAddRow;
    }

    /**
     * Returns the JavaScript callback function to call when a row is removed from the repeater.
     *
     * @return string|null
     */
    public function getRemoveRowCallback(): string {
        return $this->onRemoveRow;
    }

    /**
     * Returns the JavaScript callback function to call when a row is configured in the repeater.
     *
     * @return string|null
     */
    public function getConfigureRowCallback(): string {
       if ($this->onConfigureRow instanceof Closure) {
            return 'ajax';
        }

        return $this->onConfigureRow;
    }

    /**
     * Renders the configuration dialog for a specific row in the repeater, using either a server-side callback or the default form fields.
     *
     * @param array $rowData
     *
     * @return string
     */
    public function renderRowConfigurationDialog(array $rowData): string {
        if ($this->onConfigureRow instanceof Closure) {
            $callback = $this->onConfigureRow;
            $html = $callback($rowData, $this->getFormFields(true));

            if (is_string($html)) {
                return $this->sanitizeHtml($html);
            }
        }

        $html = '';
        $fields = $this->getFormFields(true);

        foreach ($fields as $field) {
            $fieldName = $field->getName();

            if (in_array($fieldName, array_keys($rowData))) {
                $field->default($rowData[$fieldName]);
            }

            $field->attribute('data-ref-field-name', $fieldName);
            $field->name('repeater_form_' . $fieldName);

            $html .= $field->html();
        }

        return $html;
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

        $json['rowFields'] = array_map(function($field) {
            return $field->toJson();
        }, $this->rowFields);

        $json['formFields'] = array_map(function($field) {
            return $field->toJson();
        }, $this->formFields);
        
        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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

        foreach ($this->rowFields as $field) {
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