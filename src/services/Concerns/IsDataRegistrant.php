<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;

trait IsDataRegistrant {
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
     * The item type for array items when the current item is an array (e.g. 'string', 'integer', 'object', etc.).
     *
     * @var string
     */
    protected string $itemType = ''; 

    /**
     * An array of sub-items for object and array types, used for nested settings and repeaters.
     *
     * @var array
     */
    protected array $subItems = [];

    /**
     * Valid types for settings and schema definitions.
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

    /***************************
     * Public Chainable methods
     ***************************/

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
            $itemType = !empty($this->itemType) ? $this->itemType : 'string';

            if (!in_array($itemType, $this->types)) {
                throw new \InvalidArgumentException("Invalid item_type '{$itemType}' for array setting '{$this->name}'.");
            }

            $schema['items'] = [
                'type' => $itemType,
            ];

            // Guard: subItems require object arrays
            if (!empty($this->subItems) && $itemType !== 'object') {
                throw new \InvalidArgumentException(
                    "Array setting '{$this->name}' defines subItems but item_type is not 'object'."
                );
            }

            // If not an object array, nothing more to build
            if ($itemType !== 'object') {
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
     * Sets the dot-notated path for the item.
     *
     * @param  string $path The dot-notated path for the item (e.g. 'my_array.*.child').
     *
     * @return self
     */
    final public function path(string $path = ''): self {
        $this->path = $path;
        return $this;
    }

    /**
     * Sets the name for the item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    final public function name(string $name): self {
        $name = Str::snake($name);

        $this->updatePaths($this->name, $name);

        $this->name = $name;

        $this->queue();
        return $this;
    }

    /**
     * Shorthand method to set the name and type for a string item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    final public function string(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('string');
    }

    /**
     * Shorthand method to set the name and type for a boolean item.
     *
     * @param string $name The item name.
     * 
     * @return self 
     */
    final public function boolean(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('boolean');
    }

    /**
     * Shorthand method to set the name and type for an integer item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    final public function integer(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('integer');
    }

    /**
     * Shorthand method to set the name and type for a number item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    final public function number(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('number');
    }

    /**
     * Shorthand method to set the name and type for an object item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    final public function object(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('object');
    }

    /**
     * Shorthand method to set the name and type for an array item.
     *
     * @param string $name      The item name.
     * @param Closure $callback An optional callback function to define sub-items if the array is an array of objects (i.e. itemType is 'object').
     * 
     * @return self
     */
    final public function array(string $name = '', ?Closure $callback = null): self {
        if (!empty($name)) {
            $this->name($name);
        }

        $this->type('array');

        if ($callback instanceof Closure) {
            $this->itemType('object');
            $callback($this);
            return $this;
        }

        return $this->itemType('string'); // Default to array of strings unless otherwise specified
    }

    /**
     * Explicitly defines the item type for an array setting.
     *
     * @param string $type The item type (e.g. 'string', 'integer', 'object').
     * 
     * @return self
     * @throws \InvalidArgumentException if the item type is not valid or if the current setting is not an array type.
     */
    final public function of(string $type): self {
        $type = Str::singular($type); // Allows for passing plural item types e.g. 'strings'

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid item type '{$type}' for array setting '{$this->name}'.");
        }

        $this->itemType = $type;

        $this->queue();
        return $this;
    }

    /**
     * Sets the type of value for the item.
     *
     * @param string $type The value type (e.g. 'string', 'boolean', etc.).
     * 
     * @return self
     * @throws \InvalidArgumentException if the type is not valid or if the type is already set for the item.
     */
    public function type(string $type): self {
        if (!empty($this->type)) {
            throw new \InvalidArgumentException("Type for '{$this->name}' is already set to '{$this->type}'.");
        }

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid type '{$type}' for setting '{$this->name}'.");
        }

        $this->type = $type;
        $this->args('type', $type);

        $this->queue();
        return $this;
    }

    /**
     * Sets the item type for array items when the current item is an array.    
     *
     * @param string $itemType
     *
     * @return self
     * @throws \InvalidArgumentException if the item type is not valid or if the current item is not an array type.
     */
    public function itemType(string $itemType): self {
        if (!in_array($itemType, $this->types)) {
            throw new \InvalidArgumentException("Invalid item type '{$itemType}' for array setting '{$this->name}'.");
        }

        if (($this->type ?? null) !== 'array') {
            throw new \InvalidArgumentException("Cannot set item type on non-array setting '{$this->name}'.");
        }

        $this->itemType = $itemType;

        $this->queue();
        return $this;
    }

    /**
     * Sets the label for the item.
     *
     * @param string $label The human-readable label for the item.
     * 
     * @return self
     */
    public function label(string $label): self {
        $this->args['label'] = $label;

        $this->queue();
        return $this;
    }

    /**
     * Sets the description for the item.
     *
     * @param  string $description A description of the item.
     * 
     * @return self
     */
    public function description(string $description): self {
        $this->args['description'] = $description;

        $this->queue();
        return $this;
    }

    /**
     * Sets the default value for the item.
     *
     * @param mixed $value The default value for the item.
     * 
     * @return self
     * @throws \InvalidArgumentException if the default value does not match the defined type for the item.
     */
    public function default(mixed $value): self {
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
     * @return self
     */
    public function showInRest(bool $show = true): self {
        $this->args['show_in_rest'] = $show;

        $this->queue();
        return $this;
    }

    /**
     * Merges the provided arguments with the existing arguments for the item.
     *
     * @param array|string $argsOrKey An array of arguments to merge with existing arguments or a single argument key to set.
     * @param mixed        $value The value to set if a single argument key is provided.

     * 
     * @return self
     */
    public function args(array|string $argsOrKey, mixed $value = null): self {
        if (is_array($argsOrKey)) {
            $this->args = array_merge($this->args, $argsOrKey);
        } else {
            $this->args[$argsOrKey] = $value;
        }

        $this->queue();
        return $this;
    }

    /***************************
     * Heirachical Helpers
     ***************************/
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

    /**
     * Returns whether the current item is a 'root' item.
     *
     * @return bool True if the item is a root item (i.e. has no parent), false otherwise.
     */
    public function isRoot(): bool {
        return $this->parent === null;
    }

    /**
     * Returns whether the current item can have child items (i.e. is an object or array type).
     *
     * @return bool True if the item can have child items, false otherwise.
     */
    protected function canBeParent(): bool {
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
     * @return self Returns the current instance for method chaining.
     */
    public function parent(DataRegistrant $parent): self {
        $this->parent = $parent;
        return $this;
    }

    public function add(Closure|array|null $callback = null, array $props = []): self {
        // Check we can add subitems to this item
        if (!$this->canBeParent()) {
            throw new \InvalidArgumentException("Cannot add item to '{$this->name}' because '{$this->name}' is not an object or array.");
        }

        // Check the current item is named
        if (empty($this->name) || !isset($this->name)) {
            throw new \InvalidArgumentException("Cannot add item to unnamed parent. Please set a name for the parent item before adding sub-items.");
        }

        $params = func_num_args();

        if ($params === 1 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
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

        $item = $this->makeSubItem(self::class, $props);

        $item->path($this->makeSubItemPath($formattedName))->parent($this);
        $this->subItems[] = $item;

        // Ensure array types have itemType set to object if they have sub-items
        if ($this->type === 'array' && $this->itemType !== 'object') {
            $this->itemType = 'object';
        }

        if ($callback && $callback instanceof Closure) {
            $callback($item);
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
            'provider' => $this->provider,
            'props'    => $props
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
            ? "{$basePath}.{$name}.*"
            : "{$basePath}.{$name}";
    }

    /**
     * Instantiates any sub-items that are defined as class names, replacing them with their instantiated objects.
     *
     * This method should be called in the concrete class's constructor.
     *
     * @return void
     */
    final protected function instantiateSubItems(): void {
        if (!$this->canBeParent()) {
            return;
        }

        if ($this->subItems === []) {
            return;
        }

        foreach ($this->subItems as $index => $itemClass) {
            if (is_string($itemClass)) {
                $item = $this->makeSubItem($itemClass);

                if (empty($item->name) || !isset($item->name)) {
                    $item->name(Str::snake(class_basename($itemClass)));
                }

                $item->path($this->makeSubItemPath($item->name))->parent($this);
                $this->subItems[$index] = $item;
            }
        }
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

    /***************************
     * Helpers
     ***************************/
    /**
     * Retrieves the sub-items for the current item.
     *
     * @return array The sub-items for the current item.
     */
    public function getSubItems(): array {
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
            return 'array.' . ($this->itemType !== 'object' ? 'scalar' : 'object');
        }
        return $this->type;
    }

    /**
     * Retrieves the item data type of the item if the item is an array of items.
     *
     * @return string The item data type of the item (e.g. 'string', 'integer', 'object', etc.).
     */
    public function getItemDataType(): string {
        return $this->itemType;
    }

    /**
     * Returns whether the current item has any sub-items.
     *
     * @return boolean
     */
    public function hasSubItems(): bool {
        return !empty($this->subItems);
    }
}