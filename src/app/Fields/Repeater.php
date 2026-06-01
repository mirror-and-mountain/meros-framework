<?php 

namespace MM\Meros\App\Fields;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Facades\Fields as FieldsRegister;

use MM\Meros\Facades\Context;

class Repeater extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'repeater';

    /**
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $category = 'specialised';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'table';
    
    /**
     * The root name for the repeater field, used to generate sub-field names.
     *
     * @var string
     */
    protected string $rootName = '';

    /**
     * Default class list for repeaters.
     *
     * @var array
     */
    protected array $classList = ['meros-repeater-field'];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'array.object'
    ];

    /**
     * Whether to force the field to take up the full width of its container, regardless of the width setting.
     *
     * @var bool
     */
    protected bool $forceFullWidth = true;

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
     * The name of a js callback function to use for configuring the repeater's row.
     * If unset and allowConfigure is true, a default callback will be used that opens a
     * modal with the row's fields for configuration.
     *
     * @var string
     */
    protected string $configurationCallback = '';

    /**
     * Default callback path for row configure actions.
     *
     * @var string
     */
    protected string $defaultConfigurationCallback = '$store.repeaterField.defaultConfigureRowModal';

    /**
     * The fields that belong to this repeater.
     *
     * @var array<Field>
     */
    public array $fields = [];

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

    /********************
     * Fluent Setters
     ********************/

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
     * Sets the name of a js callback function to use for configuring the repeater's row.
     *
     * @param string $callback
     *
     * @return static
     */
    public function configurationCallback(string $callback): static {
        $this->configurationCallback = $callback;
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
     * Refreshes the repeater's fields with a new array of field instances.
     *
     * @param array $fields<Field>
     *
     * @return void
     */
    public function refreshFields(array $fields): void {
        $this->fields = $fields;
    }

    /********************
     * Getters
     ********************/

    /**
     * Gets whether the repeater allows adding new rows.
     *
     * @return boolean
     */
    public function allowsAdd(): bool {
        return $this->allowAdd ?? true;
    }

    /**
     * Gets whether the repeater allows removing rows.
     *
     * @return boolean
     */
    public function allowsRemove(): bool {
        return $this->allowRemove ?? true;
    }

    /**
     * Gets whether the repeater allows reordering rows.
     *
     * @return boolean
     */
    public function allowsReorder(): bool {
        return $this->allowReorder ?? true;
    }

    /**
     * Gets whether the repeater allows configuring rows.
     * 
     * @return boolean
     */
    public function allowsConfigure(): bool {
        return $this->allowConfigure ?? true;
    }

    /**
     * Gets the name of the js callback function used for configuring the repeater's row.
     *
     * @return string
     */
    public function getConfigurationCallback(): string {
        return empty($this->configurationCallback) && $this->allowsConfigure()
            ? $this->defaultConfigurationCallback
            : $this->configurationCallback;
    }

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
    public function getFieldNames(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getName())
            ->toArray();
    }

    /**
     * Returns the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldLabels(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getLabel())
            ->toArray();
    }

    /***************************
     * Rendering
     ***************************/

    /**
     * Renders the repeater table field.
     * 
     * @param bool $showLabel Whether to show the field's label in the wrapper. Some wrappers may ignore this and always show the label, or never show the label.
     * @param bool $showHelp Whether to show the field's help text in the wrapper. Some wrappers may ignore this and always show the help text, or never show the help text.
     *
     * @return void
     */
    public function render(bool $showLabel = true, bool $showHelp = true): void {
        $wrapper = $this->resolveFieldWrapper();

        echo view($wrapper, [
            'view'      => $this->getFieldComponent(),
            'field'     => $this,
            'rows'      => $this->buildRows(),
            'showLabel' => $showLabel,
            'showHelp'  => $showHelp
        ]);
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return Context::isAdmin() ? 'meros::forms.fields.repeater-admin' : 'meros::forms.fields.repeater';
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
            $rowData = is_array($rowData) ? $rowData : [];
            $rowToken = $this->resolveRowToken($rowData, $index);
            $row = [];

            foreach ($this->fields as $field) {
                $fieldInstance = clone $field;
                
                // Store the original field name before generating the indexed name
                $baseFieldName = $fieldInstance->getName();

                $fieldInstance->attribute('data-row-index', $rowToken);
                $fieldInstance->attribute('data-base-field-name', $baseFieldName);

                $fieldInstance->id($this->generateSubFieldId($fieldInstance, $rowToken));
                $fieldInstance->name($this->generateSubFieldName($fieldInstance, $rowToken));
                
                // Look up value using the base field name, not the generated indexed name
                $fieldInstance->value($rowData[$baseFieldName] ?? null);

                // Key the row by base field name so repeater view can access with getFieldNames()
                $row[$baseFieldName] = $fieldInstance;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Builds a hidden template row for the empty-state repeater UI.
     *
     * @return array
     */
    public function buildTemplateRow(): array {
        $rows = [];
        $rowToken = $this->resolveRowToken([], 0);

        foreach ($this->fields as $field) {
            $fieldInstance = clone $field;
            $baseFieldName = $fieldInstance->getName();

            $fieldInstance->attribute('data-row-index', $rowToken);
            $fieldInstance->attribute('data-base-field-name', $baseFieldName);

            $fieldInstance->id($this->generateSubFieldId($fieldInstance, $rowToken));
            $fieldInstance->name($this->generateSubFieldName($fieldInstance, $rowToken));

            $rows[$baseFieldName] = $fieldInstance;
        }

        return $rows;
    }

    /********************
     * Helpers
     ********************/
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