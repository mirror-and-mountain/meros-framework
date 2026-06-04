<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Illuminate\Support\Str;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Admin\SettingsField;

use MM\Meros\Facades\FieldWrappers;

abstract class Field extends FeatureDefinition {
    /**
     * The field's unique slug, should be implemented by concrete field classes 
     * to provide a unique identifier for the field type.
     *
     * @var string
     */
    public string $handle;

    /**
     * The category this field belongs to, used for organising fields in the form builder UI.
     *
     * @var string
     */
    public static string $category = 'basic';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = '';

    /**
     * Whether this field is available in the FormBuilder.
     *
     * @var boolean
     */
    public static bool $showInFormBuilder = true;

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [];

    /**
     * The data type of the field's value.
     *
     * @var string
     */
    protected string $dataType = '';

    /**
     * The root name for the field, used to generate names.
     *
     * @var string
     */
    protected string $rootName = '';

    /**
     * The form row this field belongs to, if any.
     *
     * @var FormRow|null
     */
    protected ?FormRow $row = null;

    /**
     * The field's position within its parent row.
     *
     * @var integer|null
     */
    protected ?int $position = null;

    /**
     * The repeater instance this field belongs to, if any.
     *
     * @var Repeater|null
     */
    protected ?Repeater $repeater = null;

    /**
     * The SettingsField instance associated with this field, if any.
     *
     * @var SettingsField|null
     */
    public ?SettingsField $settingsField = null;

    /**
     * The field's name, which can be used for form submission and as a fallback for generating the label.
     *
     * @var string
     */
    public string $name = '';

    /**
     * The field's unique ID, which can be used for HTML attributes and as a fallback for generating the name.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * An associative array of additional HTML attributes to apply to the field's input element.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The field's label, displayed to users and used 
     * as a fallback for generating the name.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The field's help text, providing additional guidance to users.
     *
     * @var string
     */
    protected string $helpText = '';

    /**
     * The position of the field's help text, which can be 'top' or 'bottom'.
     *
     * @var string
     */
    protected string $helpTextPosition = '';

    /**
     * The field's default value, used when no explicit value is set.
     *
     * @var mixed
     */
    protected mixed $default = null;

    /**
     * The field's current value, which may be set explicitly or fall back to the default.
     *
     * @var mixed
     */
    protected mixed $value = null;

    /**
     * Whether the field is required for form submission.
     *
     * @var boolean
     */
    protected bool $required = false;

    /**
     * Whether the field is disabled and not interactive.
     *
     * @var boolean
     */
    protected bool $disabled = false;

    /**
     * An array of conditions that determine the field's visibility or behavior based on other field values.
     *
     * @var array
     */
    protected array $conditions = [];

    /**
     * The name of a js callback function to execute when the field's value changes.
     *
     * @var string
     */
    protected string $onChange = '';

    /**
     * An array of validation rules to apply to the field's value.
     *
     * @var array
     */
    protected array $rules = [];

    /**
     * For concrete field classes that support validation rules,
     * this array defines the keys that will be accepted via the rule() and rules() methods.
     *
     * @var array
     */
    protected array $supportedRules = [];

    /**
     * An array of CSS classes to apply to the field's wrapper element.
     *
     * @var array
     */
    protected array $classList = [];

    // =========================================================================
    // Contract Methods
    // =========================================================================

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);
        
        if (empty($this->id)) {
            $this->id = 'field_' . Str::substr(Str::uuid(), 0, 8);
        }
    }
    
    final protected function queue(): void {
        // Field classes don't use the queue method
    }

    // =========================================================================
    // Rendering
    // =========================================================================

        /**
     * Renders the field using its designated FieldWrapper.
     * 
     * @param bool $showLabel Whether to show the field's label in the wrapper. Some wrappers may ignore this and always show the label, or never show the label.
     * @param bool $showHelp Whether to show the field's help text in the wrapper. Some wrappers may ignore this and always show the help text, or never show the help text.
     *
     * @return void
     */
    public function render(bool $showLabel = true, bool $showHelp = true): void {
        $wrapper = $this->resolveFieldWrapper();

        echo view($wrapper, [
            'view'       => $this->getFieldComponent(),
            'field'      => $this,
            'showLabel'  => $showLabel,
            'showHelp'   => $showHelp,
        ]);
    }

    /**
     * Renders the field and returns the HTML as a string.
     *
     * @param bool $showLabel Whether to show the field's label in the wrapper. Some wrappers may ignore this and always show the label, or never show the label.
     * @param bool $showHelp Whether to show the field's help text in the wrapper. Some wrappers may ignore this and always show the help text, or never show the help text.
     *
     * @return string The rendered HTML of the field.
     */
    public function html(bool $showLabel = true, bool $showHelp = true): string {
        ob_start();
        $this->render($showLabel, $showHelp);
        return ob_get_clean();
    }

    /**
     * Retrieves the name of the Blade component responsible for rendering this field type.
     *
     * @return string
     */
    abstract public function getFieldComponent(): string;

    /**
     * Retrieves the Blade view path for the field's assigned field wrapper.
     *
     * @return string
     */
    protected function resolveFieldWrapper(): string {
        $hasSettingsField = 
            $this->settingsField !== null || 
            ($this->isSubField() && $this->repeater->settingsField !== null);
        
        if ($hasSettingsField) {
            $wrapperHandle = 'admin_settings';
        } else {
            // $wrapperHandle = Context::isAdmin() ? 'admin_default' : 'site_default';
            $wrapperHandle = 'site_default'; // Sharing in both contexts for now.
        }

        $wrapper = FieldWrappers::checkout($this->provider)->makeFrom($wrapperHandle);

        if ($wrapperHandle === 'admin_settings') {
            $this->class('meros-settings-field');
        }

        return $wrapper->getView();
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the field's ID.
     *
     * @param string $id
     *
     * @return self
     */
    public function id(string $id): self {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the field's name attribute.
     *
     * @param string $name
     *
     * @return self
     */
    public function name(string $name): self {
        $this->name = Str::replace(' ', '_', $name);
        return $this;
    }

    /**
     * Sets the root name for the field, which is used to generate names.
     *
     * @param string $rootName The root name to set for the field.
     *
     * @return self
     */
    public function rootName(string $rootName): self {
        $this->rootName = $rootName;
        return $this;
    }

    /**
     * Sets the field's data type.
     *
     * @param string $dataType The data type to set for the field (e.g., 'string', 'integer', 'array').
     *
     * @return self
     */
    public function dataType(string $dataType): self {
        $this->dataType = $dataType;
        return $this;
    }

    /**
     * Sets the field's label.
     *
     * @param string $label
     *
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the field's help text.
     *
     * @param string $helpText
     * @param string $position The position of the help text, either 'top' or 'bottom'.
     *
     * @return self
     */
    public function helpText(string $helpText, string $position = 'top'): self {
        $this->helpText = $helpText;
        $this->helpTextPosition($position);
        return $this;
    }

    /**
     * Shorthand alias for helpText() to set the field's help text.
     *
     * @param string $helpText
     * @param string $position The position of the help text, either 'top' or 'bottom'.
     *
     * @return self
     */
    public function help(string $helpText, string $position = 'top'): self {
        return $this->helpText($helpText, $position);
    }

    /**
     * Sets a default value for the field.
     *
     * @param mixed $default
     *
     * @return self
     */
    public function default(mixed $default): self {
        $this->default = $default;
        return $this;
    }

    /**
     * Sets the current value of the field.
     *
     * @param mixed $value
     *
     * @return self
     */
    public function value(mixed $value): self {
        $this->value = $value;
        return $this;
    }

    /**
     * Marks the field as required if $required is true, or optional if false.
     *
     * @param boolean $required
     *
     * @return self
     */
    public function required(bool $required = true): self {
        $this->required = $required;
        return $this;
    }

    /**
     * Disables the field if $disabled is true, or enables it if false.
     *
     * @param boolean $disabled
     *
     * @return self
     */
    public function disabled(bool $disabled = true): self {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Adds one or more CSS classes to the field's class list.
     *
     * @param string|array $classes A space-separated string or an array of CSS class names.
     * 
     * @return self
     */
    public function class(string|array $classes): self {
        $classes = is_array($classes) ? $classes : explode(' ', $classes);
        $this->classList = array_merge($this->classList, $classes);
        return $this;
    }

    /**
     * Removes one or more CSS classes from the field's class list.
     *
     * @param string|array $classes
     *
     * @return self
     */
    public function removeClass(string|array $classes): self {
        $classes = is_array($classes) ? $classes : explode(' ', $classes);
        $this->classList = array_diff($this->classList, $classes);
        return $this;
    }
    
    /**
     * Adds an additional HTML attribute to the field's input element.
     *
     * @param string $key The name of the HTML attribute (e.g., 'data-custom', 'aria-label').
     * @param mixed  $value The value of the HTML attribute. If null, the attribute will be rendered as a boolean attribute.
     *
     * @return self
     */
    public function attribute(string $key, mixed $value = null): self {
        if (is_null($value)) {
            $this->attributes[$key] = true; // For boolean attributes, set the value to true
            return $this;
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Sets the position of the field's help text.
     *
     * @param string $position Either 'top' or 'bottom'.
     *
     * @return self
     */
    public function helpTextPosition(string $position): self {
        if (in_array($position, ['top', 'bottom'])) {
            $this->helpTextPosition = $position;
        }

        return $this;
    }

    // =========================================================================
    // Context Setters
    // =========================================================================

    /**
     * Sets the field's parent form row.
     *
     * @param FormRow $row
     *
     * @return self
     */
    public function row(FormRow $row): self {
        $this->row = $row;
        return $this;
    }

    /**
     * Sets the field's position within its parent row, for reference.
     * 
     * This does not automatically reorder the fields in the row. 
     * The FormRow is responsible for ordering the fields when rendering.
     *
     * @param integer $position
     *
     * @return self
     */
    public function position(int $position): self {
        $this->position = $position;
        return $this;
    }

    /**
     * Associates the field with a repeater, marking it as a sub-field.
     *
     * @param Repeater|null $repeater The repeater to associate with this field. If null is passed, the repeater property will be set to null.
     *
     * @return self
     */
    public function repeater(Repeater|null $repeater): self {
        $this->repeater = $repeater;
        return $this;
    }

    /**
     * Attaches the field to a parent settings field instance.
     *
     * @param SettingsField $settingsField
     *
     * @return self
     */
    public function settingsField(SettingsField $settingsField): self {
        $this->settingsField = $settingsField;
        $this->settingsField->attachField($this);
        return $this;
    }

    // =========================================================================
    // Dynamic Setters
    // =========================================================================

    /**
     * Defines validation rules for the field's value.
     *
     * @param array $rules
     *
     * @return self
     */
    public function rules(array $rules): self {
        foreach ($rules as $key => $value) {
            if (in_array($key, $this->supportedRules)) {
                $this->rules[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Sets a single validation rule for the field.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function rule(string $key, mixed $value): self {
        if (in_array($key, $this->supportedRules)) {
            $this->rules[$key] = $value;
        }

        return $this;
    }

    /**
     * Defines conditions for the field that determine its visibility or behaviour 
     * based on the values of other fields. 
     *
     * @param array $conditions
     *
     * @return self
     */
    public function conditions(array $conditions): self {
        $validTypes = [
            'show',
            'hide',
            'require',
            'optional',
            'enable',
            'disable',
        ];

        $parsedConditions = [];

        foreach($conditions as $type => $config) {
            if (!in_array($type, $validTypes)) {
                continue;
            }

            $logicOperator = in_array($config['logic'] ?? 'and', ['and', 'or']) ? $config['logic'] : 'and';
            $rules         = $config['rules'] ?? [];
            $parsedRules   = [];

            foreach($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                
                $field    = $rule['field'] ?? null;
                $operator = $rule['operator'] ?? null;
                $value    = $rule['value'] ?? null;

                if ($field === null || $operator === null || $value === null) {
                    continue;
                }

                $parsedRules[] = [
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $value,
                ];
            }

            if (empty($parsedRules)) {
                continue;
            }

            $parsedConditions[$type] = [
                'logic' => $logicOperator,
                'rules' => $parsedRules,
            ];
        }    

        $this->conditions = $parsedConditions;

        return $this;
    }

    /**
     * Shorthand method to define a 'show' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to show the field.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function showWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'show' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Shorthand method to define a 'hide' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to hide the field.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function hideWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'hide' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Shorthand method to define a 'require' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to require the field.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function requireWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'require' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Shorthand method to define an 'optional' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to make the field optional.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function optionalWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'optional' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Shorthand method to define an 'enable' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to enable the field.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function enableWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'enable' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Shorthand method to define a 'disable' condition for the field.
     *
     * @param array  $rules An array of rules that determine when to disable the field.
     * @param string $logic The logic operator to apply to the rules, either 'and' or 'or'.
     *
     * @return self
     */
    public function disableWhen(array $rules, string $logic = 'and'): self {
        $logic = in_array($logic, ['and', 'or']) ? $logic : 'and';

        return $this->conditions([
            'disable' => [
                'logic' => $logic,
                'rules' => $rules,
            ]
        ]);
    }

    /**
     * Sets a JavaScript callback path to be executed when the field's value changes.
     *
     * The callback is invoked from the field blade with the native DOM change event.
     * 
     * @param string $callback
     *
     * @return self
     */
    public function onChange(string $callback): self {
        $this->onChange = $this->normaliseCallbackPath($callback);
        return $this;
    }

    /**
     * Normalises and validates JavaScript callback paths used by field interactions.
     * Returns an empty string when a callback path is invalid.
     *
     * Field onChange callbacks receive:
     * - $event: the native change event from the field input/select element.
     *
        * @return string
     */
    protected function normaliseCallbackPath(string $callback): string {
        $trimmed = trim($callback);

        if ($trimmed === '' || strlen($trimmed) > 200) {
            return '';
        }

        $pattern = '/^(?:(?:\$store|\$wire)\.)?[A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*$/';

        if (!preg_match($pattern, $trimmed)) {
            return '';
        }

        $path = $trimmed;

        if (str_starts_with($trimmed, '$store.')) {
            $path = substr($trimmed, strlen('$store.'));
        }

        if (str_starts_with($trimmed, '$wire.')) {
            $path = substr($trimmed, strlen('$wire.'));
        }

        $segments = array_values(array_filter(explode('.', $path), fn($segment) => $segment !== ''));
        $blockedSegments = ['__proto__', 'prototype', 'constructor'];

        foreach ($segments as $segment) {
            if (in_array($segment, $blockedSegments, true)) {
                return '';
            }
        }

        return $trimmed;
    }

    // =========================================================================
    // Getters
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
        $json = [
            'type'             => static::class,
            'handle'           => $this->handle,
            'properties'       => [
                'id'               => $this->getId(),
                'name'             => $this->getName(),
                'label'            => $this->getLabel(),
                'helpText'         => $this->getHelpText(),
                'helpTextPosition' => $this->getHelpTextPosition(),
                'attributes'       => $this->attributes,
                'classList'        => $this->classList,
                'default'          => $this->default,
                'required'         => $this->isRequired(),
                'disabled'         => $this->isDisabled(),
                'rules'            => $this->getRules(),
                'conditions'       => $this->getConditions(),
                'component'        => $this->getFieldComponent(),
                'compatibleDataTypes' => $this->compatibleDataTypes,
            ]
        ];

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    /**
     * Retrieves the field's data type.
     *
     * @return string
     * @throws \RuntimeException If the field does not have a data type defined.
     */
    public function getDataType(): string {
        if ($this->dataType !== '') {
            return $this->dataType;
        }

        if ($this->compatibleDataTypes[0] ?? null) {
            return Str::before($this->compatibleDataTypes[0], '.');
        }

        throw new \RuntimeException('Field must have a data type defined either in the $dataType property or the $compatibleDataTypes array.');
    }

    /**
     * Returns whether the field is a sub-field of a repeater.
     *
     * @return boolean
     */
    public function isSubField(): bool {
        return $this->repeater !== null;
    }

    /**
     * Returns the field type (handle).
     *
     * @return string
     */
    public function getType(): string {
        return $this->handle;
    }

    /**
     * Retrieves the field's ID.
     *
     * @return string
     */
    public function getId(): string {
        return empty($this->id) ? $this->getName() : $this->id;
    }

    /**
     * Retrieves the field's label, generating it from the name or ID if not explicitly set.
     *
     * @return string
     */
    public function getLabel(): string {
        if (!empty($this->label)) {
            return $this->label;
        }

        if (!empty($this->name)) {
            $this->label = Str::title(Str::replace(['-', '_', '[', ']'], ' ', $this->name));
            return $this->label;
        }

        if (!empty($this->id)) {
            $this->label = Str::title(Str::replace(['-', '_'], ' ', $this->id));
            return $this->label;
        }

        return '';
    }

    /**
     * Retrieves the field's help text.
     *
     * @return string
     */
    public function getHelpText(): string {
        return $this->helpText;
    }

    /**
     * Retrieves the position of the field's help text.
     *
     * @return string
     */
    public function getHelpTextPosition(): string {
        return empty($this->helpTextPosition) ? 'top' : $this->helpTextPosition;
    }

    /**
     * Retrieves the field's current value, falling back to the default if no value is set.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        $value = is_string($this->value) ? trim($this->value) : $this->value;

        if ($value === null || $value === '' || $value === []) {
            return is_string($this->default) ? trim($this->default) : $this->default;
        }

        return $value;
    }

    /**
     * Retrieves the field's default value.
     *
      * @return mixed
      */
    public function getDefault(): mixed {
        return $this->default;
    }

    /**
     * Retrieves the field's name, generating it from the ID or label if not explicitly set.
     * 
     * @param bool $includeRootName Whether to include the root name for sub-fields.
     *
     * @return string
     * @throws \RuntimeException If the field cannot generate a name because it lacks a name, ID, and label.
     */
    public function getName(bool $includeRootName = true): string {
        $name = null;

        if (!empty($this->name)) {
            $name = $this->name;
        }

        if ($name === null && !empty($this->id)) {
            $this->name = $this->id;
            $name = $this->name;
        }

        if ($name === null && !empty($this->label)) {
            $this->name = Str::slug($this->label);
            $name = $this->name;
        }

        if ($name !== null) {
            if ($this->isSubField() && !$includeRootName) {
                return $name;
            }

            if ($this->isSubField() && $includeRootName) {
                return Str::replace(['[', ']'], '', Str::afterLast($name, '['));
            }

            if ($includeRootName && $this->rootName !== '') {
                return "{$this->rootName}[{$name}]";
            }

            return $name;
        }

        throw new \RuntimeException('Field must have a name, id, or label to generate a name.');
    }

    /**
     * Retrieves the field's conditions array.
     *
     * @return array
     */
    public function getConditions(): array {
        return empty($this->conditions) 
            ? [
                'show' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
                'hide' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
                'require' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
                'optional' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
                'enable' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
                'disable' => [
                    'logic' => 'and',
                    'rules' => [],
                ],
            ] 
            : $this->conditions;
    }

    /**
     * Retrieves the JavaScript callback path set to execute when the field's value changes.
     * The callback is executed with the native DOM change event.
     *
     * @return string
     */
    public function getOnChange(): string {
        return !empty($this->onChange) 
            ? $this->normaliseCallbackPath($this->onChange) 
            : '';
    }

    /**
     * Returns validation rules as an array of HTML attributes that can be applied to the 
     * field's input element for client-side validation.
     *
     * @return array
     */
    public function getRuleAttributes(): array {
        $attributes = [];

        foreach ($this->rules as $key => $value) {
            if (in_array($key, $this->supportedRules)) {
                if (in_array($key, ['min', 'max', 'maxlength', 'step'])) {
                    $attributes[$key] = $value;
                    continue;
                }

                if (is_bool($value)) {
                    $attributes["data-rule-{$key}"] = $value ? 'true' : 'false';
                    continue;
                }

                $attributes["data-rule-{$key}"] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Retrieves the field's validation rules.
     *
     * @return array
     */
    public function getRules(): array {
        return $this->rules;
    }

    /**
     * Checks if the field is marked as required.
     *
     * @return boolean
     */
    public function isRequired(): bool {
        return $this->required;
    }

    /**
     * Checks if the field is marked as disabled.
     *
     * @return boolean
     */
    public function isDisabled(): bool {
        return $this->disabled;
    }

    /**
     * Retrieves the field's CSS class list as a space-separated string.
     *
     * @return string
     */
    public function classList(): string {
        return implode(' ', $this->classList);
    }

    /**
     * Retrieves the field's HTML attributes as a string of key="value" pairs.
     * 
     * @param array $exclude An array of attribute keys to exclude from the output (e.g., ['id', 'name']).
     *
     * @return string
     */
    public function attributes(array $exclude = []): string {
        $attributes = array_merge([
            'id'              => $this->getId(),
            'name'            => $this->getName(!$this->isSubField()),
            'class'           => $this->classList(),
            'disabled'        => $this->disabled,
            'aria-disabled'   => $this->disabled ? 'true' : 'false',
            'required'        => $this->required,
            'aria-required'   => $this->required ? 'true' : 'false',
            'data-field-type' => $this->handle
        ], $this->attributes, $this->getRuleAttributes());

        $rendered = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            if (is_null($value) || $value === '' || $value === false) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $rendered[] = $key;
                }
                continue;
            }

            $rendered[] = sprintf(
                '%s="%s"',
                $key,
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }

        return implode(' ', $rendered);
    }

    /**
     * Retrieves the field's variation, which is determined by the 'type' attribute if it exists.
     *
     * @return string|null
     */
    public function getVariation(): ?string {
        if (array_key_exists('type', $this->attributes)) {
            return $this->attributes['type'];
        }

        return null;
    }

    /**
     * Checks if the field is compatible with a given data type.
     *
     * @param string $dataType The data type to check compatibility with (e.g., 'string', 'integer', 'array').
     *
     * @return boolean True if the field is compatible with the data type, false otherwise.
     */
    public function isCompatibleWith(string $dataType): bool {
        return in_array($dataType, $this->compatibleDataTypes);
    }

    /**
     * Retrieves the category this field belongs to.
     *
     * @return string
     */
    public static function getCategory(): string {
        return static::$category;
    }

    /**
     * Retrieves the icon for this field.
     *
     * @return string
     */
    public static function getIcon(): string {
        return static::$icon;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Magic method to handle dynamic method calls for chaining, such as setting the section on the associated SettingsField.
     *
     * @param string $method The name of the method being called.
     * @param array  $arguments The arguments passed to the method.
     *
     * @return mixed
     * @throws \BadMethodCallException If the method does not exist on the Field class or the associated SettingsField.
     */
    public function __call(string $method, mixed $arguments) {
        // Allow dynamic getters for properties
        if ($method === 'section' && $this->settingsField !== null) {
            $this->settingsField->section(...$arguments);
            return $this;
        }

        if ($method === 'titleHTML' && $this->settingsField !== null) {
            $this->settingsField->titleHTML(...$arguments);
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }
}