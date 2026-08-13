<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;
use MM\Meros\Services\Contracts\FeatureProvider;

trait IsDataRegistrant {
    // Here...refactor root/parent to use 'container' concept. Prevent nesting container type registrants (data-type object)

    /**
     * The parent object definition for the current item, used for nested settings. 
     * Null if this is a root item.
     *
     * @var DataRegistrant|null
     */
    protected ?DataRegistrant $parent = null;

    /**
     * The name of the item, used as the key in the schema and for dot-notated paths.
     *
     * @var string
     */
    public string $name = ''; 

    /**
     * An array of arguments to be passed to the item when hooked into WordPress.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * The dot-notated path for the item, used for nested settings (e.g. 'my_array.*.child').
     *
     * @var string
     */
    protected string $path = '';

    /**
     * The type of value for the item (e.g. 'string', 'boolean', 'integer', 'number', 'array', 'object').
     *
     * @var string
     */
    protected string $type = '';

    /**
     * The data type of array items when the current item is of type 'array' (e.g. 'string', 'integer', 'object', etc.).
     *
     * @var string
     */
    protected string $arrayType = ''; 

    /**
     * An array of sub-items for object and array types, used for nested settings and repeaters.
     *
     * @var array
     */
    protected array $subItems = [];

    /**
     * Valid data types for settings and schema definitions.
     *
     * @var array<string>
     */
    final protected array $types = [
        'string', 
        'boolean', 
        'integer', 
        'number', 
        'array', 
        'object'
    ];

    use IsAdminFieldRegistrant, HasSanitizer;

    abstract public function identifier(string $identifier): static;
    abstract public function provider(): FeatureProvider;
    abstract protected function hook(): void;

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the dot-notated path for the item.
     *
     * @param  string $path The dot-notated path for the item (e.g. 'my_array.*.child').
     *
     * @return static
     */
    final public function path(string $path = ''): static {
        $this->path = $path;
        return $this;
    }

    /**
     * Sets the name for the item.
     *
     * @param string $name The item name.
     * 
     * @return static
     */
    final public function name(string $name): static {
        $name = Str::snake($name);

        $this->updatePaths($this->name, $name);

        $this->name = $name;
        $this->identifier($name);

        $this->hook();
        return $this;
    }

    /**
     * Sets the label for the item.
     *
     * @param string $label The human-readable label for the item.
     * 
     * @return static
     */
    public function label(string $label): static {
        $this->args['label'] = $label;

        $this->hook();
        return $this;
    }

    /**
     * Sets the description for the item.
     *
     * @param  string $description A description of the item.
     * 
     * @return static
     */
    public function description(string $description): static {
        $this->args['description'] = $description;

        $this->hook();
        return $this;
    }

    /**
     * Sets the default value for the item.
     *
     * @param mixed $value The default value for the item.
     * 
     * @return static
     * @throws \InvalidArgumentException if the default value does not match the defined type for the item.
     */
    public function default(mixed $value): static {
        $type = $this->type ?? null;

        if ($type) {
            $valid = match ($type) {
                'string'  => is_string($value),
                'boolean' => is_bool($value),
                'integer' => is_int($value),
                'number'  => is_numeric($value),
                'array'   => is_array($value),
                'object'  => is_array($value) || is_object($value),
                default   => true,
            };

            if (!$valid) {
                throw new \InvalidArgumentException("Invalid default value for type '{$type}' on '{$this->name}'");
            }
        }

        $this->args['default'] = $value;

        return $this;
    }

    /**
     * Sets whether the item should be exposed in the REST API.
     *
     * @param bool $show Whether to show the item in the REST API.
     * 
     * @return static
     */
    public function showInRest(bool $show = true): static {
        $this->args['show_in_rest'] = $show;

        $this->hook();
        return $this;
    }

    /**
     * Merges the provided arguments with the existing arguments for the item.
     *
     * @param array|string $argsOrKey An array of arguments to merge with existing arguments or a single argument key to set.
     * @param mixed        $value The value to set if a single argument key is provided.

     * 
     * @return static
     */
    public function args(array|string $argsOrKey, mixed $value = null): static {
        if (is_array($argsOrKey)) {
            $this->args = array_merge($this->args, $argsOrKey);
        } else {
            $this->args[$argsOrKey] = $value;
        }

        $this->hook();
        return $this;
    }

    // =========================================================================
    // Type Setters
    // =========================================================================

    /**
     * Shorthand method to set the name and type for a string item.
     *
     * @param string       $name The item name.
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static
     */
    final public function string(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('string');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for a boolean item.
     *
     * @param string       $name The item name.
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static 
     */
    final public function boolean(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('boolean');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for an integer item.
     *
     * @param string       $name The item name.
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static
     */
    final public function integer(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('integer');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for a number item.
     *
     * @param string       $name The item name.
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static
     */
    final public function number(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('number');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for an object item.
     *
     * @param string       $name The item name.
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static
     */
    final public function object(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }
        
        $this->type('object');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for an array item.
     *
     * @param string $name      The item name.
     * @param Closure $callback An optional callback function to define sub-items if the array is an array of objects (i.e. arrayType is 'object').
     * 
     * @return static
     */
    final public function array(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('array');
        $this->arrayType('string'); // Default to array of strings unless otherwise specified

        if ($callback instanceof Closure) {
            $this->arrayType('object'); // Assume object type if a callback is provided for sub-items
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Shorthand method to set the name and type for an array of scalar items (e.g. strings, integers, etc.).
     *
     * @param string $name      The item name.
     * @param Closure $callback An optional callback function to define sub-items if the array is an array of objects (i.e. arrayType is 'object').
     * 
     * @return static
     */
    final public function scalarArray(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('array');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this->arrayType('string'); // Default to array of strings unless otherwise specified
    }

    /**
     * Shorthand method to set the name and type for an array of object items.
     *
     * @param string       $name     The item name.
     * @param Closure|null $callback An optional callback function to define sub-items for the object items in the array.
     *
     * @return static
     */
    final public function objectArray(string $name = '', ?Closure $callback = null): static {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('array');
        $this->arrayType('object');

        if ($callback instanceof Closure) {
            $callback($this);
            return $this;
        }

        return $this;
    }

    /**
     * Explicitly defines the item type for an array setting.
     *
     * @param string       $type The item type (e.g. 'string', 'integer', 'object').
     * @param Closure|null $callback An optional callback function to configure the item.
     * 
     * @return static
     * @throws \InvalidArgumentException if the item type is not valid or if the current setting is not an array type.
     */
    final public function of(string $type, ?Closure $callback = null): static {
        $type = Str::singular($type); // Allows for passing plural item types e.g. 'strings'

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid item type '{$type}' for array setting '{$this->name}'.");
        }

        $this->arrayType = $type;

        if ($callback instanceof Closure) {
            $callback($this);
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the type of value for the item.
     *
     * @param string $type The value type (e.g. 'string', 'boolean', etc.).
     * 
     * @return static
     * @throws \InvalidArgumentException if the type is not valid or if the type is already set for the item.
     */
    protected function type(string $type): static {
        if (!empty($this->type)) {
            throw new \InvalidArgumentException("Type for '{$this->name}' is already set to '{$this->type}'.");
        }

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid type '{$type}' for setting '{$this->name}'.");
        }

        $this->type = $type;
        $this->args('type', $type);

        $this->hook();
        return $this;
    }

    /**
     * Sets the item type for array items when the current item is an array.    
     *
     * @param string $arrayType
     *
     * @return static
     * @throws \InvalidArgumentException if the item type is not valid or if the current item is not an array type.
     */
    protected function arrayType(string $arrayType): static {
        if (!in_array($arrayType, $this->types)) {
            throw new \InvalidArgumentException("Invalid item type '{$arrayType}' for array setting '{$this->name}'.");
        }

        if (($this->type ?? null) !== 'array') {
            throw new \InvalidArgumentException("Cannot set item type on non-array setting '{$this->name}'.");
        }

        $this->arrayType = $arrayType;

        $this->hook();
        return $this;
    }

    // =========================================================================
    // Hierarchy and Sub-Item Management
    // =========================================================================

    /**
     * Returns whether the current item is a 'root' item.
     *
     * @return bool True if the item is a root item (i.e. has no parent), false otherwise.
     */
    public function isRoot(): bool {
        return $this->parent === null;
    }

    /**
     * Returns the root item for the current item, traversing up the parent chain until a root item is found.
     *
     * @return DataRegistrant
     */
    public function getRoot(): DataRegistrant {
        $current = $this;

        while ($current->parent !== null) {
            $current = $current->parent;
        }

        return $current;
    }

    /**
     * Returns whether the current item can have child items (i.e. is an object or array type).
     *
     * @return bool True if the item can have child items, false otherwise.
     */
    public function canBeParent(): bool {
        return in_array($this->type, ['object', 'array']);
    }

    /**
     * Checks if the current item has a parent object definition.
     *
     * @return bool True if a parent is set, false otherwise.
     */
    public function hasParent(): bool {
        return $this->parent !== null;
    }

    /**
     * Sets the parent object definition for the current feature.
     *
     * @param DataRegistrant $parent The parent object definition to set.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function parent(DataRegistrant $parent): static {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Adds a sub-item to the current item, if the current item can have sub-items (i.e. is an object or array type).
     *
     * @return DataRegistrant The newly created sub-item instance.
     * @throws \InvalidArgumentException if the current item cannot have sub-items or if a sub-item with the same name already exists.
     */
    public function add(): DataRegistrant {
        // Check we can add subitems to this item
        if (!$this->canBeParent()) {
            throw new \InvalidArgumentException("Cannot add item to '{$this->name}' because '{$this->name}' is not an object or array.");
        }

        // Check the current item is named
        if (empty($this->name) || !isset($this->name)) {
            throw new \InvalidArgumentException("Cannot add item to unnamed parent. Please set a name for the parent item before adding sub-items.");
        }

        $formattedName = isset($props['name']) 
            ? Str::snake($props['name']) 
            : 'placeholder_name';

        $props['name'] = $formattedName;

        // Prevent duplicates
        foreach ($this->subItems as $child) {
            if ($child->name === $formattedName) {
                throw new \InvalidArgumentException("Cannot add '{$formattedName}' to '{$this->name}' because an item with the name '{$formattedName}' already exists at this level.");
            }
        }

        $item = $this->makeSubItem(static::class, $props);

        $item->path($this->makeSubItemPath($formattedName))->parent($this);
        $this->subItems[] = $item;

        // Ensure array types have arrayType set to object if they have sub-items
        if ($this->type === 'array' && $this->arrayType !== 'object') {
            $this->arrayType = 'object';
        }

        return $item;
    }

    /**
     * Instantiates a sub-item class with the necessary constructor arguments.
     *
     * @param string $itemClass The fully qualified class name of the sub-item to instantiate.
     * @param array  $props Optional properties to pass to the sub-item constructor.
     *
     * @return DataRegistrant The instantiated sub-item object.
     */
    final protected function makeSubItem(string $itemClass, array $props = []): DataRegistrant {
        return app($itemClass, [
            'identifier' => $props['name'] ?? 'placeholder_name',
            'provider'   => $this->provider(),
            'props'      => $props
        ]);
    }

    /**
     * Generates the dot-notated path for a new sub-item based on the current item's path and the provided sub-item name.
     *
     * @param string $name The name of the sub-item for which to generate the path.
     * 
     * @return string The generated dot-notated path for the sub-item.
      */
    final protected function makeSubItemPath(string $name): string {
        $basePath = $this->isRoot() 
            ? $this->name // Why we require this item is named.
            : $this->path;

        return $this->type === 'array'
            ? "{$basePath}.*.{$name}"
            : "{$basePath}.{$name}";
    }

    /**
     * Updates the dot-notated path for the current item and all sub-items.
     * Should be called when the name of the item changes.
     *
     * @param string $oldName The old name of the item.
     * @param string $newName The new name of the item.
     *
     * @return void
     */
    final protected function updatePaths($oldName, $newName): void {
        if (Str::endsWith($this->path, ".*")) {
            $this->path = Str::replace("{$oldName}.*", "{$newName}.*", $this->path);
        }

        else {
            $this->path = Str::replace($oldName, $newName, $this->path);
        }

        $this->walk(function ($item) use ($oldName, $newName) {
            $path = $item->getPath();
            $item->path(Str::replace($oldName, $newName, $path));
        });
    }

    /**
     * Recursively walks through all sub-items and applies the given callback function to each item.
     *
     * @param callable $callback The callback function to apply to each item.
     *
     * @return void
     */
    protected function walk(callable $callback): void {
        foreach ($this->subItems as $item) {
            $callback($item);

            if (!empty($item->subItems)) {
                $item->walk($callback);
            }
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Converts the setting instance to a schema array for use in REST API registration.
     *
     * @return array
     * @throws \InvalidArgumentException if the setting has an invalid type or item type (for arrays).
     */
    final public function toSchema(): array {
        $type = !empty($this->type) ? $this->type : 'string';

        $schema = [
            'type' => $type,
        ];

        // Add label (JSON Schema "title")
        if (array_key_exists('label', $this->args) && !empty($this->args['label'])) {
            $schema['title'] = $this->args['label'];
        }

        // Add description
        if (array_key_exists('description', $this->args) && !empty($this->args['description'])) {
            $schema['description'] = $this->args['description'];
        }

        // Guard: only object/array types can have subItems
        if (!empty($this->subItems) && !in_array($type, ['object', 'array'])) {
            throw new \InvalidArgumentException("Setting '{$this->name}' has subItems but is not an object or array type.");
        }

        // Only include default if explicitly set AND not null
        if (array_key_exists('default', $this->args) && $this->args['default'] !== null) {
            $schema['default'] = $this->args['default'];
        }

        // OBJECT
        if ($type === 'object') {
            $properties = [];

            foreach ($this->subItems as $child) {
                $properties[$child->name] = $child->toSchema();
            }

            if (!empty($properties)) {
                $schema['properties'] = $properties;
            }
        }

        // ARRAY
        if ($type === 'array') {
            $arrayType = !empty($this->arrayType) ? $this->arrayType : 'string';

            if (!in_array($arrayType, $this->types)) {
                throw new \InvalidArgumentException("Invalid item_type '{$arrayType}' for array setting '{$this->name}'.");
            }

            $schema['items'] = [
                'type' => $arrayType,
            ];

            // Guard: subItems require object arrays
            if (!empty($this->subItems) && $arrayType !== 'object') {
                throw new \InvalidArgumentException(
                    "Array setting '{$this->name}' defines subItems but item_type is not 'object'."
                );
            }

            // If not an object array, nothing more to build
            if ($arrayType !== 'object') {
                return $schema;
            }

            // Array of objects (repeatable rows)
            $properties = [];

            foreach ($this->subItems as $child) {
                $properties[$child->name] = $child->toSchema();
            }

            if (!empty($properties)) {
                $schema['items']['properties'] = $properties;
            }
        }

        return $schema;
    }

    /**
     * Retrieves the sub-items for the current item.
     *
     * @return array The sub-items for the current item.
     */
    public function getSubItems(bool $collect = false): array|Collection {
        if ($collect) {
            return collect($this->subItems);
        }

        return $this->subItems;
    }

    /**
     * Retrieves the dot-notated path for the item.
     *
     * @return string The dot-notated path for the item (e.g. 'my_array.*.child').
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Retrieves the data type of the item.
     * 
     * @param bool $arrayTypes Whether to the return the type of array items.
     *
     * @return string The data type of the item (e.g. 'string', 'integer', 'array', 'object', etc.).
     */
    public function getDataType(bool $arrayTypes = false): string {
        if ($arrayTypes && $this->type === 'array') {
            return 'array.' . ($this->arrayType !== 'object' ? 'scalar' : 'object');
        }
        return $this->type;
    }

    /**
     * Retrieves the item data type of the item if the item is an array of items.
     *
     * @return string The item data type of the item (e.g. 'string', 'integer', 'object', etc.).
     */
    public function getItemDataType(): string {
        return $this->arrayType;
    }

    /**
     * Retrieves the default value for the item, if set.
     *
     * @return mixed The default value for the item, or null if not set.
     */
    public function getDefault(): mixed {
        if (!$this->canBeParent()) {
            return $this->args['default'] ?? null;
        }

        $explicitDefault = $this->args['default'] ?? null;

        if ($this->type === 'array') {
            return is_array($explicitDefault) ? $explicitDefault : [];
        }

        $childDefaults = [];

        foreach ($this->subItems as $item) {
            $childDefaults[$item->name] = $item->getDefault();
        }

        if (!is_array($explicitDefault)) {
            return $childDefaults;
        }

        return array_replace_recursive($childDefaults, $explicitDefault);
    }

    /**
     * Returns whether the current item has any sub-items.
     *
     * @return boolean
     */
    public function hasSubItems(): bool {
        return !empty($this->subItems);
    }

    // =========================================================================
    // Magics
    // =========================================================================

    /**
     * Magic method to handle dynamic method calls for retrieving sub-items by name.
     *
     * @param string $method The name of the method being called.
     * @param array  $arguments The arguments passed to the method.
     *
     * @return mixed The sub-item if found, or the result of the callback if provided.
     * @throws \InvalidArgumentException if no sub-item is found with the given name.
     */
    public function __call(string $method, mixed $arguments) {
        if ($method === 'get') {
            $name = $arguments[0] ?? null;
            $callback = $arguments[1] ?? null;

            if (!is_string($name)) {
                throw new \InvalidArgumentException("The 'get' method requires a name argument.");
            }

            $subItem = collect($this->subItems)->firstWhere('name', $name);

            if ($subItem !== null) {
                if ($callback instanceof Closure) {
                    return $callback($subItem);
                }

                return $subItem;
            }

            return null;
        }

        throw new \BadMethodCallException("Method '{$method}' does not exist on " . static::class);
    }
}