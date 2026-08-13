<?php 

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;

use MM\Meros\Contracts\Features\Admin\SettingsField;
use MM\Meros\Contracts\Features\Components\Concerns\IsFormComponent;

use MM\Meros\App\FormComponents\Fields\Repeater;

abstract class Field extends Feature implements FormComponent {
    /**
     * The field's unique handle.
     *
     * @var string
     */
    public string $handle = '';

    // ===================================================================================
    // Field Properties (Generally converted to HTML attributes/elements when rendering)
    // ===================================================================================

    /**
     * The field's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The field's name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The field's label.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The field's description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The field's default value.
     *
     * @var string|array|null
     */
    protected string|array|null $defaultValue = null;

    /**
     * The field's wrapper view.
     *
     * @var string
     */
    protected string $wrapper = 'meros::forms.field-wrappers.admin-settings';

    /**
     * The field's view.
     *
     * @var string
     */
    protected string $view = '';

    // =========================================================================
    // Field Context Properties
    // =========================================================================

    /**
     * The field's parent Form instance, if any.
     *
     * @var Form|null
     */
    protected ?Form $form = null;

    /**
     * The id of the form associated with this field, if any.
     *
     * @var string|int|null
     */
    protected string|int|null $formId = null;

    /**
     * The field's FieldRow instance, if any.
     *
     * @var FieldRow|null
     */
    protected ?FieldRow $row = null;

    /**
     * The index of the field's associated row in its associated form, if any.
     *
     * @var int|null
     */
    protected ?int $rowIndex = null;

    /**
     * The position of the field within its associated row, if any.
     *
     * @var int|null
     */
    protected ?int $rowPosition = null;

    /**
     * The FieldGroup instance, if any.
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $group = null;

    /**
     * The id of the FieldGroup associated with this field, if any.
     *
     * @var string|null
     */
    protected ?string $groupId = null;

    /**
     * The field's associated SettingsField instance, if any.
     *
     * @var SettingsField|null
     */
    protected ?SettingsField $settingsField = null;

    /**
     * The field's associated Repeater instance, if any.
     *
     * @var Repeater|null
     */
    protected ?Repeater $repeater = null;

    /**
     * The id of the Repeater field associated with this field, if any.
     *
     * @var string|null
     */
    protected ?string $repeaterId = null;

    /**
     * Whether the field is hidden in a repeater table view, if it is part of a repeater.
     *
     * @var bool
     */
    public bool $hiddenInRepeaterTable = false;

    /**
     * Whether the field is hidden in a repeater form view, if it is part of a repeater.
     *
     * @var bool
     */
    public bool $hiddenInRepeaterForm = false;

    // =========================================================================
    // Field Supports and Compatibility Properties
    // =========================================================================

    /**
     * The field's supported features.
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * The field's primary data type.
     *
     * @var string
     */
    protected string $dataType = '';

    /**
     * An array of additional data types supported by the field.
     *
     * @var array
     */
    protected array $additionalDataTypes = ['null'];

    /**
     * The valid data types for the field.
     * 
     * @var array
     */
    final protected array $validDataTypes = [
        'string', 'integer', 'number', 'boolean', 'array.object', 'array.scalar'
    ];

    // =========================================================================
    // Static Form Builder/Row Properties
    // =========================================================================

    /**
     * Whether the field should be displayed in the form builder.
     *
     * @var bool
     */
    protected static bool $showInFormBuilder = true;

    /**
     * The field's category. Used for determining where the field is displayed in the form builder.
     *
     * @var string
     */
    protected static string $category = 'basic';

    /**
     * The field's icon. Used for displaying an icon against the field in the form builder.
     *
     * @var string
     */
    protected static string $builderIcon = '';

    /**
     * The number of row positions the field occupies in a row (maximum of 3).
     *
     * @var int
     */
    protected int $occupiesRowPositions = 1;

    use IsFormComponent;

    // =========================================================================
    // Form Builder/Row Accessors
    // =========================================================================

    /**
     * Determines whether the field should be displayed in the form builder.
     *
     * @return boolean
     */
    public static function useInFormBuilder(): bool {
        return static::$showInFormBuilder;
    }

    /**
     * Returns the field's category.
     *
     * @return string
     */
    public static function getCategory(): string {
        return static::$category;
    }

    /**
     * Returns the field's builder icon.
     *
     * @return string
     */
    public static function getBuilderIcon(): string {
        return static::$builderIcon;
    }

    /**
     * Returns the number of row positions the field occupies in a row.
     *
     * @return int
     */
    public function getRowPositions(): int {
        return $this->occupiesRowPositions > 3 ? 3 : $this->occupiesRowPositions;
    }

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Initialises the field definition. This method is called during the 
     * construction of the field definition. 
     * 
     * May be overridden by subclasses, but this is only recommended 
     * for abstract field definitions which should ensure parent::init() 
     * is before any other logic.
     * 
     * For concrete field definitions, use the configure() method instead.
     *
     * @return void
     */
    protected function init(): void {
        $idNameSuffix = Str::substr(Str::uuid(), 0, 8);

        $this->setProps($this->passedProps, ['defaultValue', 'attributes', 'classes', 'form', 'group'], [
            'id'   => "mforms-field-{$idNameSuffix}", 
            'name' => "mforms_field_{$idNameSuffix}"
        ]);

        $this->setSerializableProperties([
            'id',
            'name',
            'label',
            'placeholder',
            'description',
            'defaultValue',
            'required',
            'disabled',
            'readonly',
            'classes',
            'classString',
            'attributes',
            'attributeString',
            'formId',
            'rowIndex',
            'rowPosition',
            'groupId',
            'repeaterId',
            'hiddenInRepeaterTable',
            'hiddenInRepeaterForm',
            'wrapper',
            'view',
        ], false);
    }

    /**
     * Performs post-user configuration checks and normalises properties.
     *
     * @return void
     * @throws \InvalidArgumentException if the field hasn't declared the required properties.
     */
    final protected function whenConfigured(): void {
        if (empty($this->handle)) {
            $this->handle = Str::snake(class_basename(static::class));
        }

        if (empty($this->dataType)) {
            throw new \InvalidArgumentException("A valid data type must be defined for field '{$this->handle}'.");
        }

        if (empty($this->view)) {
            throw new \InvalidArgumentException("A view must be defined for field '{$this->handle}'.");
        }

        if (empty($this->wrapper)) {
            throw new \InvalidArgumentException("A wrapper view must be defined for field '{$this->handle}'.");
        }

        $this->normaliseProperties();
    }

    /**
     * Normalises any properties that require special handling if they were passed to the definition's constructor.
     *
     * @return void
     */
    protected function normaliseProperties(): void {
        // Set associated form if passed
        if (array_key_exists('form', $this->passedProps) && $this->passedProps['form'] instanceof Form) {
            $this->form($this->passedProps['form']);
            $this->formId = $this->form->getId();
        }

        // Set associated group if passed
        if (array_key_exists('group', $this->passedProps) && $this->passedProps['group'] instanceof FieldGroup) {
            $this->group($this->passedProps['group']);
            $this->groupId = $this->group->getId();
        }

        // Normalise and set the default value if provided, or normalise against null if not provided
        if (array_key_exists('defaultValue', $this->passedProps)) {
            $this->defaultValue = $this->normaliseValue($this->passedProps['defaultValue']);
        } else if (array_key_exists('default', $this->passedProps)) {
            $this->defaultValue = $this->normaliseValue($this->passedProps['default']);
        } else if ($this->defaultValue === null) {
            $this->defaultValue = $this->normaliseValue(null);
        }

        // Set required attribute if passed
        if ($this->passedProps['required'] ?? false) {
            $this->required(true);
        }

        // Set readonly attribute if passed
        if ($this->passedProps['readonly'] ?? false) {
            $this->readonly(true);
        }

        // Set disabled attribute if passed
        if ($this->passedProps['disabled'] ?? false) {
            $this->disabled(true);
        }

        // Set placeholder attribute if passed and supported
        if (!empty($this->passedProps['placeholder'] ?? '')) {
            $this->placeholder($this->passedProps['placeholder']);
        }

        // Normalise and set classes and attributes if passed
        $this->normaliseClasses($this->passedProps['classes'] ?? null);
        $this->normaliseAttributes($this->passedProps['attributes'] ?? null);

        // Set form builder attributes if passed
        if ($this->passedProps['formBuilderDefaultControl'] ?? false) {
            $this->attribute('data-form-builder-default-control', 'true');
        }

        // Set standard attributes
        $this->attribute('data-field-type', $this->handle);
    }

    /**
     * Normalises the field's classes property, ensuring it is an array of class names.
     *
     * @param array|string|null $classes The classes to normalise.
     *
     * @return void
     */
    private function normaliseClasses(array|string|null $classes): void {
        if (is_string($classes)) {
            $this->classes = array_merge($this->classes, explode(' ', $classes));
        }

        else if (is_array($classes)) {
            $this->classes = array_merge($this->classes, $classes);
        }
    }

    /**
     * Normalises the field's attributes property, ensuring it is an associative array of attribute names and values.
     *
     * @param array|string|null $attributes The attributes to normalise.
     *
     * @return void
     */
    private function normaliseAttributes(array|string|null $attributes): void {
        if (is_string($attributes)) {
            $this->attributes = array_merge($this->attributes, $this->parseAttributesString($attributes));
        }

        else if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (is_int($key)) {
                    // If the key is an integer, treat the value as a boolean attribute
                    $attributes[$value] = true;
                    unset($attributes[$key]);
                }
            }

            $this->attributes = array_merge($this->attributes, $attributes);
        }
    }

    /**
     * Parses a string of HTML attributes into an associative array.
     *
     * @param string $attributesString The string of HTML attributes.
     *
     * @return array An associative array of attributes.
     */
    private function parseAttributesString(string $attributesString): array {
        $attributes = [];

        // Looking for key="value" or key=value or key (boolean attribute)
        $pattern = '/(\w+)(?:="([^"]*)")?/';

        preg_match_all($pattern, $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] ?? true;
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Defines the field's handle, which is sometimes referred to as the field 'type.'
     * Concrete Field instances should set this property in their configure() method, otherwise
     * the field will be assigned a handle based on the class name.
     * 
     * @param string $type The handle (or 'type') to set.
     * 
     * @return void
     */
    final protected function type(string $type): void {
        $this->handle = Str::snake($type);
    }

    /**
     * Sets the field's view for rendering. 
     * 
     * This method should be called in the field definition's configure() method.
     *
     * @param string $view The view to set for the field.
     *
     * @return void
     */
    final protected function view(string $view): void {
        $this->view = $view;
    }

    /**
     * Sets the field's wrapper view for rendering. 
     *
     * @param string $wrapper The wrapper view to set for the field.
     *
     * @return void
     */
    final public function wrapper(string $wrapper): void {
        $this->wrapper = $wrapper;
    }

    /**
     * Sets the field's primary data type. This method must be called in the field definition's configure() method.
     *
     * @param string $type
     *
     * @return void
     */
    final protected function dataType(string $type): void {
        if (!in_array($type, $this->validDataTypes)) {
            throw new \InvalidArgumentException("Invalid data type '{$type}' for field '{$this->handle}'.");
        }

        $this->dataType = $type;

        if (in_array($type, $this->additionalDataTypes)) {
            $this->additionalDataTypes = array_filter($this->additionalDataTypes, fn($t) => $t !== $type);
        }
    }

    /**
     * Sets additional data types supported by the field. This can be called by the field definition's configure() method.
     * 
     * If an empty array is passed, the definition's additionalDataTypes will be reset to ['null'].
     *
     * @param array $dataTypes
     *
     * @return void
     * @throws \InvalidArgumentException if any of the provided data types are not valid.
     */
    final protected function additionalDataTypes(array $dataTypes): void {
        if (empty($dataTypes)) {
            $this->additionalDataTypes = ['null'];
        }

        foreach ($dataTypes as $type) {
            if (!in_array($type, $this->validDataTypes)) {
                throw new \InvalidArgumentException("Invalid additional data type '{$type}' for field '{$this->handle}'.");
            }
        }

        $this->additionalDataTypes = array_unique(array_merge($this->additionalDataTypes, $dataTypes));
    }

    /**
     * Checks if the field is compatible with a given data type.
     *
     * @param string $dataType The data type to check compatibility with.
     *
     * @return bool True if the field is compatible with the given data type, false otherwise.
     */
    final public function isCompatibleWithDataType(string $dataType): bool {
        if ($dataType === $this->dataType) {
            return true;
        }

        if (in_array($dataType, $this->additionalDataTypes)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the field's primary data type.
     *
     * @return string
     */
    final public function getDataType(): string {
        return $this->dataType;
    }

    /**
     * Adds a supported feature to the field. Should be called in the configure() method 
     * of the subclass to define which features are supported by the field.
     *
     * @param string $support
     *
     * @return void
     */
    final protected function addSupport(string $support): void {
        if (!in_array($support, $this->supports)) {
            $this->supports[] = $support;
        }
    }

    /**
     * Adds multiple supported features to the field. Should be called in the configure() method 
     * of the subclass to define which features are supported by the field.
     *
     * @param array $supports
     *
     * @return void
     */
    final protected function addSupports(array $supports): void {
        foreach ($supports as $support) {
            $this->addSupport($support);
        }
    }

    /**
     * Removes a supported feature from the field. Should be called in the configure() method 
     * of the subclass to define which features are supported by the field.
     *
     * @param string $support
     *
     * @return void
     */
    final protected function removeSupport(string $support): void {
        $this->supports = array_filter($this->supports, fn($s) => $s !== $support);
    }

    /**
     * Checks if the field supports a given feature.
     *
     * @param string $feature
     *
     * @return bool
     */
    final public function supports(string $feature): bool {
        return in_array($feature, $this->supports);
    }

    /**
     * Sets how many row positions the field occupies in a row. 
     * This should be called in the configure() method of the subclass.
     *
     * @param integer $positions
     *
     * @return void
     */
    final protected function occupiesRowPositions(int $positions): void {
        if ($positions < 1) {
            $this->occupiesRowPositions = 1;
        }

        if ($positions > 3) {
            $this->occupiesRowPositions = 3;
        }

        $this->occupiesRowPositions = $positions;
    }

    // =========================================================================
    // Attribute Methods
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        $this->type($identifier);
        return $this;
    }

    final public function getIdentifier(): string {
        return $this->handle;
    }

    /**
     * Returns the field's handle.
     *
     * @return string
     */
    final public function getHandle(): string {
        return $this->getIdentifier();
    }

    /**
     * Sets the field's id.
     *
     * @param string $id The id to set.
     * @return static
     */
    public function id(string $id): static {
        $this->id = Str::slug($id);

        if (empty($this->name) || Str::startsWith($this->name, 'mforms_field_')) {
            $this->name = Str::replace('-', '_', $this->id);
        }

        return $this;
    }

    /**
     * Returns the field's id.
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Sets the field's name.
     *
     * @param string $name The name to set.
     * @return static
     */
    public function name(string $name): static {
        $this->name = Str::snake($name);
        return $this;
    }

    /**
     * Returns the field's name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Sets the field's label.
     *
     * @param string $label The label to set.
     * @return static
     */
    public function label(string $label): static {
        $this->label = $label;
        return $this;
    }

    /**
     * Returns the field's label.
     *
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * Sets the field's placeholder, if supported.
     *
     * @param string $placeholder The placeholder to set.
     * @return static
     */
    public function placeholder(string $placeholder): static {
        if (!$this->supports('placeholder')) {
            return $this;
        }

        $this->attribute('placeholder', $placeholder);
        return $this;
    }

    /**
     * Returns the field's placeholder, if supported and set.
     *
     * @return string|null
     */
    public function getPlaceholder(): ?string {
        return $this->attributes['placeholder'] ?? null;
    }

    /**
     * Sets the field's description, if supported.
     *
     * @param string $description The description to set.
     * @return static
     */
    public function description(string $description): static {
        if (!$this->supports('description')) {
            return $this;
        }

        $this->description = $description;
        return $this;
    }

    /**
     * Returns the field's description, if supported and set.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Sets whether the field is required, if supported.
     *
     * @param bool $isRequired Whether the field is required.
     * @return static
     */
    public function required(bool $isRequired = true): static {
        if (!$this->supports('required')) {
            return $this;
        }

        if ($isRequired) {
            $this->attribute('required', true);
            $this->attribute('aria-required', 'true');
        } else {
            $this->removeAttribute('required');
            $this->removeAttribute('aria-required');
        }

        return $this;
    }

    /**
     * Checks if the field is required, if supported.
     *
     * @return bool
     */
    public function isRequired(): bool {
        return $this->supports('required') && isset($this->attributes['required']);
    }

    /**
     * Sets whether the field is readonly, if supported.
     *
     * @param bool $isReadonly Whether the field is readonly.
     * @return static
     */
    public function readonly(bool $isReadonly = true): static {
        if (!$this->supports('readonly')) {
            return $this;
        }

        if ($isReadonly) {
            $this->attribute('readonly', 'readonly');
            $this->attribute('aria-readonly', 'true');
        } else {
            $this->removeAttribute('readonly');
            $this->removeAttribute('aria-readonly');
        }

        return $this;
    }

    /**
     * Checks if the field is readonly, if supported.
     *
     * @return bool
     */
    public function isReadonly(): bool {
        return $this->supports('readonly') && isset($this->attributes['readonly']);
    }

    /**
     * Sets whether the field is disabled, if supported.
     *
     * @param bool $isDisabled Whether the field is disabled.
     * @return static
     */
    public function disabled(bool $isDisabled = true): static {
        if (!$this->supports('disabled')) {
            return $this;
        }

        if ($isDisabled) {
            $this->attribute('disabled', 'disabled');
            $this->attribute('aria-disabled', 'true');
        } else {
            $this->removeAttribute('disabled');
            $this->removeAttribute('aria-disabled');
        }

        return $this;
    }

    /**
     * Checks if the field is disabled, if supported.
     *
     * @return bool
     */
    public function isDisabled(): bool {
        return $this->supports('disabled') && isset($this->attributes['disabled']);
    }

    // =========================================================================
    // Value Setters
    // =========================================================================

    /**
     * Sets the field's default value.
     *
     * @param mixed $value The default value to set.
     * 
     * @return static
     */
    public function default(mixed $value): static {
        $this->defaultValue = $this->normaliseValue($value);
        $this->whenDefaultSet();

        return $this;
    }

    /**
     * Checks if a given value is compatible with the field's data types and returns a 
     * normalised version of the value if possible. By default, this method will normalise 
     * scalar values to strings and null values to empty strings or arrays, depending on the 
     * field's primary data type.
     * 
     * If the value is not compatible with the field's data types, an exception will be thrown.
     *
     * @param mixed $value The value to check.
     * 
     * @return mixed The value if compatible, otherwise throws an exception.
     * @throws \InvalidArgumentException if the value is not compatible with the field's data types.
     */
    protected function normaliseValue(mixed $value): mixed {
        $dataType = match (gettype($value)) {
            'string'  => 'string',
            'integer' => 'integer',
            'float'   => 'number',
            'double'  => 'number',
            'boolean' => 'boolean',
            'array'   => array_is_list($value) ? 'array.scalar' : 'array.object',
            'NULL'    => 'null',
            default   => throw new \InvalidArgumentException("Unsupported value type '" . gettype($value) . "' for field '{$this->handle}'."),
        };

        $isCompatible = $dataType === $this->dataType || in_array($dataType, $this->additionalDataTypes);

        if (!$isCompatible) {
            throw new \InvalidArgumentException("Invalid value type '{$dataType}' for field '{$this->handle}'.");
        }

        if (in_array($this->dataType, ['string', 'integer', 'number', 'boolean']) && 
            in_array($dataType, ['array.object', 'array.scalar']) === false
        ) {
            if ($dataType === 'null') {
                return ''; // Normalise null to empty string for scalar types
            } else {
                return (string) $value; // Normalise to string for scalar types
            }
        }

        if (in_array($this->dataType, ['array.scalar', 'array.object']) && $dataType === 'null') {
            return []; // Normalise null to empty array for array types
        }
        
        return $value; // No processing currently for array types.
    }

    /**
     * Returns the field's default value, cast to the appropriate data type if applicable.
     *
     * @return mixed
     */
    public function getDefaultValue(): mixed {
        return $this->castValue($this->defaultValue, $this->dataType);
    }

    /**
     * Casts a scalar value to the requested data type. This can be used to store values when saving to the database.
     *
     * @param mixed  $value The value to cast.
     * @param string $type The data type to cast the value to.
     *
     * @return mixed The casted value.
     */
    protected function castValue(mixed $value, string $type): mixed {
        switch ($type) {
            case 'string':
                return (string) $value;
            case 'integer':
                return (int) $value;
            case 'number':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            default:
                return $value; // No casting for array types or unsupported types
        }
    }

    /**
     * Performs additional actions after the default value is set. This method can be overridden by subclasses to perform additional actions after the default value is set.
     *
     * @return void
     */
    protected function whenDefaultSet(): void {
        // This method can be overridden by subclasses to perform additional actions after the default value is set.
    }

    // =========================================================================
    // Context Methods
    // =========================================================================

    /**
     * Returns the field's type (handle).
     *
     * @return string
     */
    final public function getType(): string {
        return $this->handle;
    }

    /**
     * Sets the id of the form associated with this field.
     *
     * @param Form $form The form instance to associate with the field definition.
     * @return static
     */
    final public function form(Form $form): static {
        $this->form = $form;
        $this->formId = $form->getId();
        return $this;
    }

    /**
     * Returns the field's associated Form instance, if any.
     *
     * @return Form|null
     */
    final public function getForm(): ?Form {
        return $this->form;
    }

    /**
     * Determines whether the field is associated with a Form instance.
     *
     * @return bool
     */
    final public function hasForm(): bool {
        return $this->form !== null;
    }

    /**
     * Sets the field's associated FieldRow instance and its index and position.
     *
     * @param FieldRow $row The FieldRow instance to associate with the field definition.
     * @return static
     */
    final public function row(FieldRow $row): static {
        $this->row = $row;
        return $this;
    }

    /**
     * Sets the position of the field within its associated row.
     *
     * @param int $position The position to set.
     * @return static
     */
    final public function rowPosition(int $position): static {
        $this->rowPosition = $position;
        return $this;
    }

    /**
     * Returns the field's associated FieldRow instance, if any.
     *
     * @return FieldRow|null
     */
    final public function getRow(): ?FieldRow {
        return $this->row;
    }

    /**
     * Determines whether the field is part of a FieldRow.
     *
     * @return bool
     */
    final public function isInRow(): bool {
        return $this->row !== null;
    }

    /**
     * Sets the field's associated FieldGroup instance.
     *
     * @param FieldGroup $group The FieldGroup instance to associate with the field definition.
     * @return static
     */
    final public function group(FieldGroup $group): static {
        $this->group = $group;
        $this->groupId = $group->getId();
        return $this;
    }

    /**
     * Returns the field's associated FieldGroup instance, if any.
     *
     * @return FieldGroup|null
     */
    final public function getGroup(): ?FieldGroup {
        return $this->group;
    }

    /**
     * Determines whether the field is part of a FieldGroup.
     *
     * @return bool
     */
    final public function isInGroup(): bool {
        return $this->group !== null;
    }

    /**
     * Sets the field's associated SettingsField instance.
     *
     * @param SettingsField $settingsField The SettingsField instance to associate with the field definition.
     * @return static
     */
    final public function settingsField(SettingsField $settingsField): static {
        $this->settingsField = $settingsField;
        return $this;
    }

    /**
     * Returns the field's associated SettingsField instance, if any.
     *
     * @return SettingsField|null
     */
    final public function getSettingsField(): ?SettingsField {
        return $this->settingsField;
    }

    /**
     * Determines whether the field is associated with a SettingsField.
     *
     * @return bool
     */
    final public function isSettingsField(): bool {
        return $this->settingsField !== null;
    }

    /**
     * Sets the field's associated Repeater instance.
     *
     * @param Repeater $repeater The Repeater instance to associate with the field definition.
     * @return static
     */
    final public function repeater(Repeater $repeater, string $repeaterId): static {
        $this->repeater = $repeater;
        $this->repeaterId = $repeaterId;
        return $this;
    }

    /**
     * Returns the field's associated Repeater instance, if any.
     *
     * @return Repeater|null
     */
    final public function getRepeater(): ?Repeater {
        return $this->repeater;
    }

    /**
     * Determines whether the field is nested within a Repeater field.
     *
     * @return bool
     */
    final public function isRepeaterField(): bool {
        return $this->repeater !== null;
    }

    /**
     * Sets whether the field should be hidden in a repeater table view.
     *
     * @param bool $hide Whether to hide the field in a repeater table view.
     * @return static
     */
    final public function hideInRepeaterTable(bool $hide = true): static {
        $this->hiddenInRepeaterTable = $hide;
        return $this;
    }

    /**
     * Determines whether the field is hidden in a repeater table view.
     *
     * @return bool
     */
    final public function isHiddenInRepeaterTable(): bool {
        return $this->hiddenInRepeaterTable;
    }

    /**
     * Sets whether the field should be hidden in a repeater form view.
     *
     * @param bool $hide Whether to hide the field in a repeater form view.
     * @return static
     */
    final public function hideInRepeaterForm(bool $hide = true): static {
        $this->hiddenInRepeaterForm = $hide;
        return $this;
    }

    /**
     * Determines whether the field is hidden in a repeater form view.
     *
     * @return bool
     */
    final public function isHiddenInRepeaterForm(): bool {
        return $this->hiddenInRepeaterForm;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Renders the field's HTML output using the specified wrapper view and properties.
     *
     * @param array $properties      The properties to pass to the view.
     * @param bool  $mergeProperties Whether to merge the provided properties with the field's serialized properties.
     *
     * @return void
     */
    public function render(array $properties = [], bool $mergeProperties = false): void {
        $wrapper = $this->wrapper;

        if ($mergeProperties) {
            $properties = array_merge(
                $this->filterSerializedProperties($this->toArray()['properties'] ?? []), 
                $properties
            );
        }

        else {
            $properties = empty($properties) 
                ? $this->filterSerializedProperties($this->toArray()['properties'] ?? []) 
                : $properties;
        }
        
        echo view($wrapper, [
            'type'       => $this->handle,
            'properties' => $properties
        ]);
    }

    /**
     * Returns the field's HTML output as a string.
     *
     * @param array $properties      The properties to pass to the view.
     * @param bool  $mergeProperties Whether to merge the provided properties with the field's serialized properties.
     *
     * @return string The rendered HTML output of the field.
     */
    public function html(array $properties = [], bool $mergeProperties = false): string {
        ob_start();
        $this->render($properties, $mergeProperties);

        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }
}