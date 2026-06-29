<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Illuminate\Support\Str;
use Livewire\Wireable;

use MM\Meros\App\Fields\Input;
use MM\Meros\App\Fields\Choice;
use MM\Meros\App\Fields\Repeater;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\FormRow;
use MM\Meros\Services\Contracts\Admin\SettingsField;

use MM\Meros\Facades\FieldWrappers;
use MM\Meros\Facades\Fields;
use MM\Meros\Facades\Framework;

abstract class Field extends FeatureDefinition implements Wireable {
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
     * The field's name, which can be used for form submission and as a fallback for generating the label.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The field's unique ID, which can be used for HTML attributes and as a fallback for generating the name.
     *
     * @var string
     */
    public string $id = '';

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
     * @var string|null
     */
    protected ?string $helpText = null;

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
     * An array of CSS classes to apply to the field's wrapper element.
     *
     * @var array
     */
    protected array $classList = [];

    /**
     * An array of features supported by the field.
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * An associative array of additional HTML attributes to apply to the field's input element.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * An array of conditions that determine the field's visibility or behavior based on other field values.
     *
     * @var array
     */
    protected array $conditions = [];

    /**
     * An array of validation rules to apply to the field's value.
     *
     * @var array
     */
    protected array $rules = [];

    /**
     * Whether the field has been automatically set as required due to an enforcing rule.
     *
     * @var boolean
     */
    protected bool $mustBeRequired = false;

    /**
     * Whether to show the minimum value hint for the field, if applicable.
     *
     * @var bool|null
     */
    public ?bool $showMinHint = null;

    /**
     * Whether to show the maximum value hint for the field, if applicable.
     *
     * @var bool|null
     */
    public ?bool $showMaxHint = null;

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
     * The ID of the form this field belongs to, if any.
     *
     * @var string|int|null
     */
    protected string|int|null $formId = null;

    /**
     * The form row this field belongs to, if any.
     *
     * @var FormRow|null
     */
    protected ?FormRow $row = null;

    /**
     * The index of the field's parent row within the field's current form or group.
     *
     * @var integer|null
     */
    protected ?int $rowIndex = null;

    /**
     * The field's position within its parent row.
     *
     * @var integer|null
     */
    protected ?int $rowPosition = null;

    /**
     * The field group this field belongs to, if any.
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $group = null;

    /**
     * The ID of the field's parent group.
     *
     * @var string|null
     */
    protected ?string $groupId = null;

    /**
     * The repeater instance this field belongs to, if any.
     *
     * @var Repeater|null
     */
    protected ?Repeater $repeater = null;

    /**
     * The ID of the field's parent repeater, if any.
     *
     * @var string|null
     */
    protected ?string $repeaterId = null;

    /**
     * The SettingsField instance associated with this field, if any.
     *
     * @var SettingsField|null
     */
    public ?SettingsField $settingsField = null;

    use Concerns\SanitizesHtml;

    // =========================================================================
    // Initialisation
    // =========================================================================

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);

        $this->initialise();
        $this->initialiseRules();
        $this->initialiseAttributes();

        if (array_key_exists('attributes', $props) && is_array($props['attributes'])) {
            $this->attributes = array_merge($this->attributes ?? [], $props['attributes']);
        }
        
        if (empty($this->id)) {
            $this->id = 'field-' . Str::substr(Str::uuid(), 0, 8);

            if (empty($this->name)) {
                $this->name = Str::replace('-', '_', $this->id);
            }
        }

        if (empty($this->name)) {
            $this->name = 'field_' . Str::substr(Str::uuid(), 0, 8);

            if (empty($this->id)) {
                $this->id = Str::slug($this->name);
            }
        }

        if ($this->supports('required') && isset($props['required'])) {
            $this->attribute('required', $props['required']);
        }

        if ($this->supports('disabled') && isset($props['disabled'])) {
            $this->attribute('disabled', $props['disabled']);
        }

        if ($this->supports('helpText') && isset($props['helpText'])) {
            $this->helpText = $props['helpText'];
        }

        if ($this->supports('helpText') && $this->helpText === null) {
            $this->helpText = '';
        }
    }
    
    final protected function queue(): void {
        // Field classes don't use the queue method
    }

    /**
     * Can be used to set required properties, such as the field's handle and compatible data types.
     *
     * @return void
     */
    protected function initialise(): void {
        // For concrete field classes to implement if needed
    }

    /**
     * Initialises available rules for field types based on the field's compatible data types.
     *
     * @return void
     */
    protected function initialiseRules(): void {
        if ($this->supportsStringRules()) {
            $this->addSupport('rules');

            $ruleType = isset($this->rules['type']['value']) && in_array($this->rules['type']['value'], ['chars', 'words']) 
                ? $this->rules['type']['value'] 
                : 'chars';

            $this->rules['type'] = [
                'value'       => $ruleType,
                'label'       => 'Type',
                'description' => 'The type of counting to use for minimum and maximum rules. "chars" counts characters, while "words" counts words.',
                'message'     => 'The value must be a valid :type.'
            ];

            $this->rules['max-chars'] = [
                'value'       => isset($this->rules['max-chars']['value']) ? $this->rules['max-chars']['value'] : null,
                'label'       => 'Maximum Characters',
                'description' => 'The maximum number of characters allowed for this field.',
                'message'     => 'The value must not exceed :max-chars characters.'
            ];
            $this->rules['min-chars'] = [
                'value'       => isset($this->rules['min-chars']['value']) ? $this->rules['min-chars']['value'] : null,
                'label'       => 'Minimum Characters',
                'description' => 'The minimum number of characters allowed for this field.',
                'message'     => 'The value must be at least :min-chars characters.'
            ];
            $this->rules['max-words'] = [
                'value'       => isset($this->rules['max-words']['value']) ? $this->rules['max-words']['value'] : null,
                'label'       => 'Maximum Words',
                'description' => 'The maximum number of words allowed for this field.',
                'message'     => 'The value must not exceed :max-words words.'
            ];
            $this->rules['min-words'] = [
                'value'       => isset($this->rules['min-words']['value']) ? $this->rules['min-words']['value'] : null,
                'label'       => 'Minimum Words',
                'description' => 'The minimum number of words allowed for this field.',
                'message'     => 'The value must be at least :min-words words.'
            ];
        }

        if ($this->supportsNumberRules()) {
            $this->addSupport('rules');

            $this->rules['max'] = [
                'value'       => isset($this->rules['max']['value']) ? $this->rules['max']['value'] : null,
                'label'       => 'Maximum Value',
                'description' => 'The maximum value allowed for this field.',
                'message'     => 'The value must not exceed :max.'
            ];
            $this->rules['min'] = [
                'value'       => isset($this->rules['min']['value']) ? $this->rules['min']['value'] : null,
                'label'       => 'Minimum Value',
                'description' => 'The minimum value allowed for this field.',
                'message'     => 'The value must be at least :min.'
            ];
        }

        if ($this->supportsArrayRules()) {
            $this->addSupport('rules');

            $this->rules['max-items'] = [
                'value'       => isset($this->rules['max-items']['value']) ? $this->rules['max-items']['value'] : null,
                'label'       => 'Maximum Number of Items',
                'description' => 'The maximum number of items allowed for this field.',
                'message'     => 'The value must not exceed :max-items items.'
            ];
            $this->rules['min-items'] = [
                'value'       => isset($this->rules['min-items']['value']) ? $this->rules['min-items']['value'] : null,
                'label'       => 'Minimum Number of Items',
                'description' => 'The minimum number of items allowed for this field.',
                'message'     => 'The value must be at least :min-items items.'
            ];
        }
    }

    /**
     * Helper to determine if the field supports string-based rules, based on its compatible data types and handle.
     *
     * @return boolean
     */
    protected function supportsStringRules(): bool {
        $dataTypes = $this->compatibleDataTypes;

        return in_array('string', $dataTypes) && !in_array($this->handle, ['date', 'time']) && !is_subclass_of($this, Choice::class);
    }

    /**
     * Helper to determine if the field supports number-based rules, based on its compatible data types.
     *
     * @return boolean
     */
    protected function supportsNumberRules(): bool {
        $dataTypes = $this->compatibleDataTypes;

        return in_array('integer', $dataTypes) || 
               in_array('float', $dataTypes) || 
               in_array('decimal', $dataTypes);
    }

    /**
     * Helper to determine if the field supports array-based rules, based on its compatible data types.
     *
     * @return boolean
     */
    protected function supportsArrayRules(): bool {
        $dataTypes = $this->compatibleDataTypes;

        return in_array('array.scalar', $dataTypes) || in_array('array.object', $dataTypes);
    }

    /**
     * Initialises available attributes for field types based on the field's 
     * compatible data types and supported features.
     *
     * @return void
     */
    protected function initialiseAttributes(): void {
        if ($this->supports('placeholder') && !isset($this->attributes['placeholder'])) {
            $this->attribute('placeholder', '');
        }

        if ($this->supports('required') && !isset($this->attributes['required'])) {
            $this->attribute('required', false);
        }

        if ($this->supports('disabled') && !isset($this->attributes['disabled'])) {
            $this->attribute('disabled', false);
        }

        $dataTypes = $this->compatibleDataTypes;

        if (!isset($this->attributes['step']) && (
            in_array('integer', $dataTypes) || 
            in_array('float', $dataTypes) || 
            in_array('decimal', $dataTypes)
        )) {
            $this->attribute('step', 'any');
        }
    }

    /**
     * Adds a support to the field's list of supports.
     *
     * @param string $feature
     *
     * @return void
     */
    protected function addSupport(string $feature): void {
        if (!in_array($feature, $this->supports)) {
            $this->supports[] = $feature;
        }
    }

    /**
     * Adds multiple supports to the field's list of supports.
     *
     * @param array $features
     *
     * @return void
     */
    protected function addSupports(array $features): void {
        foreach ($features as $feature) {
            $this->addSupport($feature);
        }
    }

    /**
     * Removes a support from the field's list of supports.
     *
     * @param string $feature
     *
     * @return void
     */
    protected function removeSupport(string $feature): void {
        if (in_array($feature, $this->supports)) {
            $this->supports = array_diff($this->supports, [$feature]);
        }
    }

    /**
     * Removes multiple supports from the field's list of supports.
     *
     * @param array $features
     *
     * @return void
     */
    protected function removeSupports(array $features): void {
        foreach ($features as $feature) {
            $this->removeSupport($feature);
        }
    }

    /**
     * Converts the field instance into a format suitable for Livewire rendering.
     *
     * @return array
     */
    public function toLivewire(): array {
        return $this->toJson();
    }

    /**
     * Reconstructs a field instance from Livewire data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromLivewire($data): self {
        $props = $data['properties'] ?? [];

        return new static(
            Framework::get(),
            $props
        );
    }

    /**
     * Alias for fromLivewire() to initialize a field instance from an array of data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function initFromData(array $data): self {
        return self::fromLivewire($data);
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

        if ($wrapped) {
            $wrapper = $this->resolveFieldWrapper();
            echo view($wrapper, $props);
        }

        else {
            echo view($parsedConfig['component'] ?? $this->getFieldComponent(), $props);
        }
    }

    /**
     * Renders the field and returns the HTML as a string.
     *
     * @param bool  $wrapped Whether to render the field within its wrapper. If false, only the field's input component will be rendered without any wrapper or additional elements.
     * @param array $props   An optional array of properties for rendering the field. This can include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return string The rendered HTML of the field.
     */
    public function html(bool $wrapped = true, array $props = []): string {
        ob_start();
        $this->render($wrapped, $props);

        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }

    /**
     * Retrieves the Blade view path for the field's assigned field wrapper.
     *
     * @return string
     */
    protected function resolveFieldWrapper(): string {
        $hasSettingsField = 
            $this->settingsField !== null || 
            ($this->isInRepeater() && $this->repeater->settingsField !== null);
        
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
            'view'            => $parsedProps['component'] ?? $this->getFieldComponent(),
            'field'           => $this,
            'type'            => $this->handle,
            'id'              => $parsedProps['id'],
            'name'            => $parsedProps['name'],
            'label'           => $parsedProps['label'],
            'helpText'        => $parsedProps['helpText'],
            'value'           => $this->getValue(),
            'hasRules'        => $this->hasRules(),
            'serialisedRules' => json_encode($parsedProps['rules']),
            'rules'           => $parsedProps['rules'],
            'showMinHint'     => $parsedProps['showMinHint'],
            'showMaxHint'     => $parsedProps['showMaxHint'],
            'mustBeRequired'  => $this->mustBeRequired,
            'isSubField'      => $this->isInRepeater(),
            'attributes'      => $this->attributes($parsedProps['attributes'] ?? [], $parsedProps['excludeAttributes'] ?? []),
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
        return [
            'id'                => is_string($props['id'] ?? null) ? $props['id'] : $this->getId(),
            'name'              => is_string($props['name'] ?? null) ? $this->getName(!$this->isInRepeater(), $props['name']) : $this->getName(!$this->isInRepeater()),
            'label'             => is_string($props['label'] ?? null) ? $props['label'] : (is_bool($props['label'] ?? null) ? false : $this->getLabel()),
            'helpText'          => is_string($props['helpText'] ?? null) ? $props['helpText'] : (is_bool($props['helpText'] ?? null) ? false : $this->getHelpText()),
            'showMinHint'       => is_bool($props['showMinHint'] ?? null) ? $props['showMinHint'] : $this->showMinHint,
            'showMaxHint'       => is_bool($props['showMaxHint'] ?? null) ? $props['showMaxHint'] : $this->showMaxHint, 
            'attributes'        => is_array($props['attributes'] ?? null) ? $props['attributes'] : [],
            'excludeAttributes' => is_array($props['excludeAttributes'] ?? null) ? $props['excludeAttributes'] : [],
            'rules'             => is_array($props['rules'] ?? null) ? $props['rules'] : $this->getRules(),
            'component'         => is_string($props['component'] ?? null) ? $props['component'] : null,
        ];
    }

    /**
     * Retrieves the name of the Blade component responsible for rendering this field type.
     *
     * @return string
     */
    abstract public function getFieldComponent(): string;

    // =========================================================================
    // Form Builder Integration
    // =========================================================================

    /**
     * Retrieves the name of the Blade component to use for rendering the field's default value in the 
     * form builder's field settings panel. By default, this returns the same component as getFieldComponent(), 
     * but can be overridden by fields that require a different component for rendering default values in the settings panel.
     *
     * @return string
     */
    public function getDefaultValueControl(): string {
        return $this->getFieldComponent();
    }

    /**
     * Renders the field's default value control using the component specified by getDefaultValueControl().
     *
     * @return void
     */
    public function renderDefaultValueControl(): void {
        $component = $this->getDefaultValueControl();
        
        $this->render(true, [
            'id'                => $this->getId() . '-default',
            'name'              => $this->getName() . '_default',
            'label'             => 'Default Value',
            'helpText'          => "The field's default value.",
            'attributes'        => ['data-default-value-control' => 'true', 'data-field-id' => $this->getId()],
            'excludeAttributes' => ['id', 'required', 'aria-required', 'disabled', 'aria-disabled'],
            'rules'             => [],
            'component'         => $component
        ]);
    }

    /**
     * Renders the field's default value control and returns the HTML as a string.
     *
     * @return string The rendered HTML of the default value control.
     */
    public function getDefaultValueControlHtml(): string {
        ob_start();
        $this->renderDefaultValueControl();
        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }

    /**
     * Renders the field's rule controls and returns the HTML as a string.
     *
     * @return string
     */
    public function getRuleControlsHtml(): string {
        if (!$this->supports('rules')) {
            return '';
        }

        $html = '';

        if ($this->hasRule('type')) {
            $typeField = Fields::checkout($this->provider)->makeFrom('select', [
                'id'       => $this->getId() . '-rule-type',
                'name'     => $this->getName() . '_rule_type',
                'label'    => 'Rule Type',
                'helpText' => 'The type of counting to use for minimum and maximum rules. "chars" counts characters, while "words" counts words.',
                'value'    => $this->rules['type']['value'] ?? 'chars',
                'attributes' => [
                    'data-rule-control' => 'true', 
                    'data-field-id'     => $this->getId(), 
                    'data-rule-name'    => 'type',
                    '@change'           => 'updateRuleProperty($event.target.dataset.ruleName, $event.target.value)'
                ],
                'options'  => [
                    'chars' => 'Characters',
                    'words' => 'Words'
                ],
            ]);

            $html .= $typeField->html();
        }

        $rules = collect($this->rules)->sortKeys()->toArray();

        foreach ($rules as $ruleName => $ruleConfig) {
            if ($ruleName === 'type') {
                continue;
            }

            $ruleType = $this->getRule('type');

            if ($ruleType !== null) {
                $ruleType = $ruleType['value'] ?? 'chars';
            }

            if (Str::contains($ruleName, 'chars') && $ruleType === 'words') {
                continue;
            }

            if (Str::contains($ruleName, 'words') && $ruleType === 'chars') {
                continue;
            }

            $field = Fields::checkout($this->provider)->makeFrom('number', [
                'id'         => $this->getId() . "-rule-{$ruleName}",
                'name'       => $this->getName() . "_rule_{$ruleName}",
                'label'      => $ruleConfig['label'] ?? Str::title(Str::replace(['-', '_'], ' ', $ruleName)),
                'value'      => $ruleConfig['value'] ?? null,
                'helpText'   => $ruleConfig['description'] ?? null,
                'attributes' => [
                    'data-rule-control' => 'true', 
                    'data-field-id'     => $this->getId(), 
                    'data-rule-name'    => $ruleName,
                    '@change'           => 'updateRuleProperty($event.target.dataset.ruleName, $event.target.value)'
                ],
            ]);

            $field->rule('min', 0);

            $html .= $field->html();
        }

        return $html;
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
        $this->name = Str::lower(Str::replace(' ', '_', $name));
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
     *
     * @return self
     */
    public function helpText(string $helpText): self {
        if ($this->supports('helpText')) {
            $this->helpText = $helpText;
        }

        return $this;
    }

    /**
     * Shorthand alias for helpText() to set the field's help text.
     *
     * @param string $helpText
     *
     * @return self
     */
    public function help(string $helpText): self {
        return $this->helpText($helpText);
    }

    /**
     * Sets a default value for the field.
     *
     * @param mixed $default
     *
     * @return self
     */
    public function default(mixed $default): self {
        $isDynamic = is_string($default) && 
            Str::startsWith($default, '{{') && 
            Str::endsWith($default, '}}');

        if ($isDynamic && $this->supports('dynamicDefault')) {
            $this->default = $default;
            return $this;
        }

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
     * Sets the placeholder text for the field, if supported.
     *
     * @param string $placeholder
     *
     * @return self
     */
    public function placeholder(string $placeholder): self {
        if ($this->supports('placeholder')) {
            $this->attribute('placeholder', $placeholder);
        }

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
        if ($this->supports('required')) {
            $this->attribute('required', $required);
        }

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
        if ($this->supports('disabled')) {
            $this->attribute('disabled', $disabled);
        }

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
     * Sets whether to show the minimum value hint for the field, if applicable.
     *
     * @param boolean $show
     *
     * @return self
     */
    public function showMinHint(bool $show = true): self {
        $this->showMinHint = $show;
        return $this;
    }

    /**
     * Sets whether to show the maximum value hint for the field, if applicable.
     *
     * @param boolean $show
     *
     * @return self
     */
    public function showMaxHint(bool $show = true): self {
        $this->showMaxHint = $show;
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
     * Removes an additional HTML attribute from the field's input element.
     *
     * @param string $key The name of the HTML attribute to remove (e.g., 'data-custom', 'aria-label').
     *
     * @return self
     */
    public function removeAttribute(string $key): self {
        if (array_key_exists($key, $this->attributes)) {
            unset($this->attributes[$key]);
        }
        return $this;
    }

    // =========================================================================
    // Context Setters
    // =========================================================================

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
     * Sets the field's parent form row.
     *
     * @param FormRow|null $row      The parent form row to associate with this field. If null is passed, the row property will be set to null.
     * @param int|null     $rowIndex An optional index for the parent row, used for reference. This does not automatically link the field to a row instance.
     * @param int|null     $position An optional position for the field within the parent row, used for reference. This does not automatically position the field within the row instance.
     *
     * @return self
     */
    public function row(?FormRow $row, ?int $rowIndex = null, ?int $position = null): self {
        $this->row = $row;
        $this->rowIndex = $rowIndex;
        $this->rowPosition = $position;
        return $this;
    }

    /**
     * Sets the field's parent group.
     *
     * @param FieldGroup|null $group The parent group to associate with this field. If null is passed, the group property will be set to null.
     * @param string|null     $groupId An optional ID for the parent group, used for reference. This does not automatically link the field to a group instance.
     *
     * @return self
     */
    public function group(?FieldGroup $group, ?string $groupId = null): self {
        $this->group = $group;
        $this->groupId = $groupId;
        return $this;
    }

    /**
     * Associates the field with a repeater, marking it as a sub-field.
     *
     * @param Repeater|null $repeater The repeater to associate with this field. If null is passed, the repeater property will be set to null.
     * @param string|null   $repeaterId An optional ID for the parent repeater, used for reference. This does not automatically link the field to a repeater instance.
     *
     * @return self
     */
    public function repeater(?Repeater $repeater, ?string $repeaterId = null): self {
        $this->repeater = $repeater;
        $this->repeaterId = $repeaterId;
        return $this;
    }

    /**
     * Sets whether to hide the field in a repeater's table view, making the field available only in the repeater's config dialog.
     *
     * @param boolean $hide
     *
     * @return self
     */
    public function hideInRepeaterTable(bool $hide = true): self {
        if ($hide) {
            $this->attribute('data-hide-in-repeater-table', 'true');
        } else {
            $this->removeAttribute('data-hide-in-repeater-table');
        }

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
    public function rules(array|null $rules): self {
        foreach ($rules as $key => $config) {
            $this->rule(
                $key, 
                $config['value'] ?? null, 
                $config['label'] ?? '', 
                $config['message'] ?? ''
            );
        }

        return $this;
    }

    /**
     * Sets a single validation rule for the field.
     *
     * @param string $key
     * @param mixed  $value
     * @param string $message
     *
     * @return self
     */
    public function rule(string $key, mixed $value, string $label = '', string $message = ''): self {
        if (in_array($key, array_keys($this->rules))) {
            if ($key === 'type' && $this->hasRule('type')) {
                $value = in_array($value, ['chars', 'words']) ? $value : 'chars';

                if ($value === 'chars') {
                    $this->removeRules(['min-words', 'max-words']);
                }

                else if ($value === 'words') {
                    $this->removeRules(['min-chars', 'max-chars']);
                }
            }

            $this->rules[$key] = [
                'value'   => $value,
                'label'   => !empty($label) ? $label : $this->rules[$key]['label'],
                'message' => !empty($message) ? $message : $this->rules[$key]['message']
            ];

            if ($this->supports('required') && Str::startsWith($key, 'min-')) {
                if (is_numeric($value) && ((int)$value > 0 || (float)$value > 0)) {
                    $this->required(true);
                    $this->mustBeRequired = true;
                }

                else if ((is_numeric($value) || is_null($value)) && ((int)$value === 0 || (float)$value === 0) || is_null($value)) {
                    $this->mustBeRequired = false;
                }
            }
        }

        return $this;
    }

    /**
     * Removes a validation rule from the field.
     *
     * @param string $key
     *
     * @return self
     */
    public function removeRule(string $key): self {
        if (in_array($key, array_keys($this->rules))) {
            unset($this->rules[$key]);
        }

        return $this;
    }

    /**
     * Removes multiple validation rules from the field.
     *
     * @param array $keys
     *
     * @return self
     */
    public function removeRules(array $keys): self {
        foreach ($keys as $key) {
            $this->removeRule($key);
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

    // =========================================================================
    // Property Getters
    // =========================================================================

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

    /**
     * Returns the field type (handle).
     *
     * @return string
     */
    public function getType(): string {
        return $this->handle;
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
     * Retrieves the field's ID.
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Retrieves the field's name.
     * 
     * @param bool $includeRootName Whether to include the root name for sub-fields.
     *
     * @return string
     * @throws \RuntimeException If the field does not have a name.
     */
    public function getName(bool $includeRootName = true, string $overrideName = ''): string {
        if ($this->name === '' && $overrideName === '') {
            throw new \RuntimeException('Field must have a name to generate a name attribute.');
        }

        $name = $overrideName !== '' ? $overrideName : $this->name;

        if ($this->isInRepeater() && !$includeRootName) {
            return $name;
        }

        if ($this->isInRepeater() && $includeRootName) {
            return Str::replace(['[', ']'], '', Str::afterLast($name, '['));
        }

        if ($includeRootName && $this->rootName !== '') {
            return "{$this->rootName}[{$name}]";
        }

        return $name;
    }

    /**
     * Retrieves the field's label, generating it from the field's handle if not explicitly set.
     *
     * @return string
     */
    public function getLabel(): string {
        if (!empty($this->label)) {
            return $this->label;
        }

        return Str::title(Str::replace(['-', '_'], ' ', $this->handle));
    }

    /**
     * Retrieves the field's help text.
     *
     * @return string|null
     */
    public function getHelpText(): ?string {
        return $this->supports('helpText') ? $this->helpText : null;
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
     * @param array $extra An array of additional attributes to merge into the output.
     * @param array $exclude An array of attribute keys to exclude from the output (e.g., ['id', 'name']).
     *
     * @return string
     */
    public function attributes(array $extra = [], array $exclude = []): string {
        $attributes = array_merge([
            'class'           => $this->classList(),
            'disabled'        => $this->isDisabled(),
            'aria-disabled'   => $this->isDisabled() ? 'true' : 'false',
            'required'        => $this->isRequired(),
            'aria-required'   => $this->isRequired() ? 'true' : 'false',
            'data-field-type' => $this->handle,
            'data-field-name' => $this->getName(),
        ], $extra, $this->attributes, $this->getRuleAttributes());
        

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
     * Filters out specific attributes from an attributes string.
     *
     * @param string $attributes The original attributes string to filter.
     * @param array  $filters An array of attribute keys to remove from the attributes string (e.g., ['id', 'name']).
     *
     * @return string The filtered attributes string with specified attributes removed.
     */
    public function filterAttributes(string $attributes, array $filters): string {
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $filters)) . ')(?:="[^"]*")?/';
        return preg_replace($pattern, '', $attributes);
    }

    /**
     * Checks if the field has any validation rules defined.
     *
     * @return boolean
     */
    public function hasRules(): bool {
        if (empty($this->rules)) {
            return false;
        }

        foreach ($this->rules as $_ => $rule) {
            if (isset($rule['value']) && $rule['value'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a specific validation rule is defined for the field.
     *
     * @param string $rule The name of the validation rule to check (e.g., 'required', 'min', 'max').
     *
     * @return boolean True if the rule is defined and has a non-null value; false otherwise.
     */
    public function hasRule(string $rule): bool {
        return isset($this->rules[$rule]) && isset($this->rules[$rule]['value']) && $this->rules[$rule]['value'] !== null;
    }

    // =========================================================================
    // Context Getters
    // =========================================================================

    /**
     * Returns whether the field is a sub-field of a repeater.
     *
     * @return boolean
     */
    public function isInRepeater(): bool {
        return $this->repeater !== null;
    }

    /**
     * Returns whether the field is marked to be hidden in a repeater's table view.
     *
     * @return boolean
     */
    public function isHiddenInRepeaterTable(): bool {
        return $this->attributes['data-hide-in-repeater-table'] ?? false;
    }

    /**
     * Returns whether the field is part of a field group.
     *
     * @return boolean
     */
    public function isInGroup(): bool {
        return $this->group !== null;
    }

    /**
     * Retrieves the ID of the field's parent group, if any.
     *
     * @return string|null
     */
    public function getGroupId(): ?string {
        return $this->groupId;
    }

    // =========================================================================
    // Dynamic Property Getters
    // =========================================================================

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
     * Returns validation rules as an array of HTML attributes that can be applied to the 
     * field's input element for client-side validation.
     *
     * @return array
     */
    public function getRuleAttributes(): array {
        $attributes = [];

        foreach ($this->rules as $key => $config) {
            if (!isset($config['value'])) {
                continue;
            }

             if (in_array($key, ['min', 'max'])) {
                $attributes[$key] = $config['value'];
                $attributes["data-rule-{$key}"] = $config['value'];
                continue;
            }

            if (is_bool($config['value'])) {
                $attributes["data-rule-{$key}"] = $config['value'] ? 'true' : 'false';
                continue;
            }

            $attributes["data-rule-{$key}"] = $config['value'];

            if ($key === 'max-chars' && is_numeric($config['value'])) {
                $attributes['maxlength'] = $config['value'];
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
        $rules = [];

        foreach ($this->rules as $key => $config) {
            if (isset($config['value']) && $config['value'] !== null) {
                $label = $config['label'] ?? Str::title(Str::replace(['-', '_'], ' ', $key));

                if (isset($config['message']) && $config['message'] !== '') {
                    $rules[$key] = [
                        'value'   => $config['value'],
                        'message' => Str::replace(":{$key}", $config['value'], $config['message']),
                        'label'   => $label 
                    ];
                } 
                
                else {
                    $rules[$key] = [
                        'value'   => $config['value'],
                        'label'   => $label
                    ];
                }
            }
        }

        return $rules;
    }

    /**
     * Retrieves the value of a specific validation rule for the field, if it exists.
     *
     * @param string $rule
     *
     * @return mixed
     */
    public function getRuleValue(string $rule): mixed {
        return $this->rules[$rule]['value'] ?? null;
    }

    /**
     * Retrieves the configuration of a specific validation rule for the field, if it exists.
     *
     * @param string $rule
     *
     * @return array|null
     */
    public function getRule(string $rule): ?array {
        return $this->rules[$rule] ?? null;
    }

    /**
     * Checks if the field is marked as required.
     *
     * @return bool|null
     */
    public function isRequired(): ?bool {
        return $this->supports('required')
            ? (array_key_exists('required', $this->attributes) ? $this->attributes['required'] : false)
            : null;
    }

    /**
     * Checks if the field is marked as disabled.
     *
     * @return bool|null
     */
    public function isDisabled(): ?bool {
        return $this->supports('disabled')
            ? (array_key_exists('disabled', $this->attributes) ? $this->attributes['disabled'] : false)
            : null;
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
        $json = [
            'type'             => static::class,
            'handle'           => $this->handle,
            'properties'       => [
                'type'             => $this->handle,
                'class'            => static::class,
                'id'               => $this->getId(),
                'name'             => $this->getName(),
                'label'            => $this->getLabel(),
                'helpText'         => $this->getHelpText(),
                'attributes'       => $this->attributes,
                'supports'         => $this->supports,
                'classList'        => $this->classList,
                'default'          => $this->default,
                'required'         => $this->isRequired(),
                'mustBeRequired'   => $this->mustBeRequired,
                'disabled'         => $this->isDisabled(),
                'hasRules'         => $this->hasRules(),
                'rules'            => $this->getRules(),
                'showMinHint'      => $this->showMinHint === null ? false : $this->showMinHint,
                'showMaxHint'      => $this->showMaxHint === null ? true : $this->showMaxHint,
                'conditions'       => $this->getConditions(),
                'isInputType'      => is_subclass_of($this, Input::class),
                'isChoiceType'     => is_subclass_of($this, Choice::class),
                'isMultiSelect'    => array_key_exists('multiple', $this->attributes),
                'formId'           => $this->formId,
                'isInRepeater'     => $this->isInRepeater(),
                'hideInRepeater'   => $this->attributes['data-hide-in-repeater-table'] ?? false,
                'isInGroup'        => $this->isInGroup(),
                'groupId'          => $this->groupId,
                'repeaterId'       => $this->repeaterId,
                'rowIndex'         => $this->rowIndex,
                'rowPosition'      => $this->rowPosition,
                'component'        => $this->getFieldComponent(),
            ]
        ];

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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
     * Checks if the field supports a given feature.
     *
     * @param string $feature The feature to check (e.g., 'multiple', 'advanced').
     *
     * @return bool True if the feature is supported, false otherwise.
     */
    protected function supports(string $feature): bool {
        return in_array($feature, $this->supports);
    }

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