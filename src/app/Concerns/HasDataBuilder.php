<?php 

namespace MM\Meros\App\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\Contracts\DataRegistrar;
use MM\Meros\App\Support\Helpers\DataBuilder;

trait HasDataBuilder {
    // Parent reference for nested builders
    public ?DataRegistrar $parent = null;

    public string  $name = ''; 
    public ?string $path = null;
    public array   $subItems = [];
    public string  $type; // e.g. string, object, array, etc.
    public string  $itemType; // for arrays, the type of items (e.g. string, object, etc.)

    // Valid data types
    protected array $types = [
        'string', 
        'boolean', 
        'integer', 
        'number', 
        'array', 
        'object'
    ];

    use HasDataFields;

    /***************************
     * Core Builder Methods
     ***************************/

    /**
     * Returns a new DataBuilder instance scoped to the current feature and optional path.
     * Public alias for builder().
     * 
     * @param  Closure|null $callback Optional callback to configure the builder instance.
     *
     * @return DataBuilder
     */
    public function configure(?Closure $callback = null): DataBuilder {
        if (isset($this->type) && !in_array($this->type, ['object', 'array'])) {
            throw new \InvalidArgumentException("Builder can only be used on 'object' or 'array' types. '{$this->type}' given.");
        }

        if ($callback) {
            $callback($this->builder());
        }
        
        return $this->builder();
    }

    /**
     * Returns a new DataBuilder instance scoped to the current feature and optional path.
     *
     * @return DataBuilder A new DataBuilder instance for building nested settings or schema.
     * @throws \BadMethodCallException if the builder is called on a non-root item or an item with an invalid type.
     * @throws \InvalidArgumentException if the item has an invalid type or item type (for arrays).
     */
    protected function builder(): DataBuilder {
        $type = $this->args['type'] ?? null;

        // Must be root
        if (!$this->isRoot()) {
            throw new \BadMethodCallException('Builder can only be used on root settings.');
        }

        // Must be object or array
        if (!in_array($type, ['object', 'array'])) {
            throw new \InvalidArgumentException("Builder can only be used on 'object' or 'array' types. '{$type}' given.");
        }

        // Ensure valid name
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Builder requires a valid root setting name.');
        }

        // Always reset path from source of truth (name)
        $basePath = $this->name;

        // Array = repeatable → append * (but don't mutate original path permanently)
        $builderPath = $type === 'array'
            ? "{$basePath}.*"
            : $basePath;

        // Ensure schema knows this is an object array
        if ($type === 'array') {
            $this->args['item_type'] = $this->args['item_type'] ?? 'object';
            $this->itemType = $this->args['item_type'];
        }

        return app(DataBuilder::class, [
            'root'    => $this,
            'path'    => $builderPath,
            'isArray' => $type === 'array',
        ]);
    }

    /**
     * Converts the setting instance to a schema array for use in REST API registration.
     *
     * @return array
     * @throws \InvalidArgumentException if the setting has an invalid type or item type (for arrays).
     */
    public function toSchema(): array {
        $type = $this->args['type'] ?? 'string';

        $schema = [
            'type' => $type,
        ];

        // Add label (JSON Schema "title")
        if (!empty($this->args['label'])) {
            $schema['title'] = $this->args['label'];
        }

        // Add description
        if (!empty($this->args['description'])) {
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
            $itemType = $this->args['item_type'] ?? 'string';

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

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the dot-notated path for the item.
     *
     * @param  string|null $path
     *
     * @return self
     */
    public function path(?string $path): self {
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
    public function name(string $name): self {
        $this->name = Str::snake($name);

        $this->setReady();
        return $this;
    }

    /**
     * Shorthand method to set the name and type for a string item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    public function string(string $name = ''): self {
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
    public function boolean(string $name = ''): self {
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
    public function integer(string $name = ''): self {
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
    public function number(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        return $this->type('number');
    }

    /**
     * Shorthand method to set the name and type for an array item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    public function array(string $name = ''): self {
        if (!empty($name)) {
            $this->name($name);
        }

        // Default to 'string' item type. 
        // Can be overridden later with of() method.
        $this->itemType('string');

        return $this->type('array');
    }

    /**
     * Explicitly defines the item type for an array setting.
     *
     * @param string $type The item type (e.g. 'string', 'integer', 'object').
     * 
     * @return self
     * @throws \InvalidArgumentException if the item type is not valid or if the current setting is not an array type.
     */
    public function of(string $type): self {
        $type = Str::singular($type); // Allows for passing plural item types e.g. 'strings'

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid item type '{$type}' for array setting '{$this->name}'.");
        }

        $this->args['item_type'] = $type;
        $this->itemType = $type;

        $this->setReady();
        return $this;
    }

    /**
     * Shorthand method to set the name and type for an object item.
     *
     * @param string   $name The item name.
     * @param ?Closure $callback Optional callback to configure the object item.
     * 
     * @return self
     */
    public function object(string $name, ?Closure $callback = null): self {
        $this->name($name)->type('object');

        if ($callback) {
            $callback($this->configure());
        }

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
        if (isset($this->type)) {
            throw new \InvalidArgumentException("Type for '{$this->name}' is already set to '{$this->type}'.");
        }

        if (!in_array($type, $this->types)) {
            throw new \InvalidArgumentException("Invalid type '{$type}' for setting '{$this->name}'.");
        }

        $this->args['type'] = $type;
        $this->type = $type;

        $this->setReady();
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

        if (($this->args['type'] ?? null) !== 'array') {
            throw new \InvalidArgumentException("Cannot set item type on non-array setting '{$this->name}'.");
        }

        $this->args['item_type'] = $itemType;
        $this->itemType = $itemType;

        $this->setReady();
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

        $this->setReady();
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

        $this->setReady();
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

        $this->setReady();
        return $this;
    }

    /**
     * Merges the provided arguments with the existing arguments for the item.
     *
     * @param array $args An associative array of arguments to merge with the existing item arguments.
     * 
     * @return self
     */
    public function args(array $args): self {
        $this->args = array_merge($this->args, $args);

        $this->setReady();
        return $this;
    }

    /***************************
     * Helpers
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
    protected function isRoot(): bool {
        return $this->parent === null;
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
     * @param  DataRegistrar $parent The parent object definition to set.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function parent(DataRegistrar $parent): self {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Finds the parent object definition for a given dot-notated path.
     *
     * @param  string $path The dot-notated path to find the parent for (e.g. 'my_array.*.child').
     * 
     * @return self The parent object definition for the specified path, or the current instance if no parent is found.
     */
    protected function findParentForPath(string $path): self {
        $segments = explode('.', $path);
        array_pop($segments); // remove current item

        if (empty($segments)) {
            return $this;
        }

        $current = $this;

        foreach ($segments as $segment) {
            foreach ($current->subItems as $child) {
                if ($child->name === $segment) {
                    $current = $child;
                    continue 2;
                }
            }
        }

        return $current;
    }

    /**
     * Retrieves the data type of the item.
     *
     * @return string|null The data type of the item (e.g. 'string', 'integer', 'array', 'object', etc.) or null if not set.
     */
    public function getDataType(): ?string {
        return isset($this->type) ? $this->type : ($this->args['type'] ?? null);
    }

    /**
     * Retrieves the item data type of the item if the item is an array of items.
     *
     * @return string|null The item data type of the item (e.g. 'string', 'integer', 'object', etc.) or null if not set.
     */
    public function getItemDataType(): ?string {
        return isset($this->itemType) ? $this->itemType : ($this->args['item_type'] ?? null);
    }
}