<?php 

namespace MM\Meros\App\Concerns;

use Exception;
use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\Support\DataBuilder;
use MM\Meros\App\Contracts\DataRegistrar;

trait HasDataBuilder {
    // Parent reference for nested builders
    public ?DataRegistrar $parent = null;

    public string  $name = ''; 
    public ?string $path = null;
    public array   $subItems = [];
    public string  $type; // e.g. string, object, array, etc.

    // Valid data types
    protected array $types = [
        'string', 
        'boolean', 
        'integer', 
        'number', 
        'array', 
        'object'
    ];

    protected ?Closure $layout = null;

    /***************************
     * Core Builder Methods
     ***************************/

    /**
     * Public alias for builder().
     *
     * @return DataBuilder
     */
    public function define(): DataBuilder {
        return $this->builder();
    }

    /**
     * Returns a new DataBuilder instance scoped to the current feature and optional path.
     *
     * @return DataBuilder A new DataBuilder instance for building nested settings or schema.
     * @throws Exception If the builder is used on a feature that is not of type 'object' or 'array', or is not root.
     */
    protected function builder(): DataBuilder {
        $type = $this->args['type'] ?? null;

        // Must be root
        if (!$this->isRoot()) {
            throw new Exception('Builder can only be used on root settings.');
        }

        // Must be object or array
        if (!in_array($type, ['object', 'array'])) {
            throw new Exception("Builder can only be used on 'object' or 'array' types. '{$type}' given.");
        }

        // Ensure valid name
        if (empty($this->name)) {
            throw new Exception('Builder requires a valid root setting name.');
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
            throw new Exception("Setting '{$this->name}' has subItems but is not an object or array type.");
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
                throw new Exception("Invalid item_type '{$itemType}' for array setting '{$this->name}'.");
            }

            $schema['items'] = [
                'type' => $itemType,
            ];

            // Guard: subItems require object arrays
            if (!empty($this->subItems) && $itemType !== 'object') {
                throw new Exception(
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
     * @param  string $name The item name.
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
    public function string(string $name): self {
        return $this->name($name)->type('string');
    }

    /**
     * Shorthand method to set the name and type for a boolean item.
     *
     * @param string $name The item name.
     * 
     * @return self 
     */
    public function boolean(string $name): self {
        return $this->name($name)->type('boolean');
    }

    /**
     * Shorthand method to set the name and type for an integer item.
     *
     * @param  string $name The item name.
     * 
     * @return self
     */
    public function integer(string $name): self {
        return $this->name($name)->type('integer');
    }

    /**
     * Shorthand method to set the name and type for a number item.
     *
     * @param  string $name The item name.
     * 
     * @return self
     */
    public function number(string $name): self {
        return $this->name($name)->type('number');
    }

    /**
     * Shorthand method to set the name and type for an array item.
     *
     * @param string $name The item name.
     * 
     * @return self
     */
    public function array(string $name): self {
        return $this->name($name)->type('array');
    }

    /**
     * Explicitly defines the item type for an array setting.
     *
     * @param  string $type The item type (e.g. 'string', 'integer', 'object').
     * @return self
     */
    public function of(string $type): self {
        if (!in_array($type, $this->types)) {
            throw new Exception("Invalid item type '{$type}' for array setting '{$this->name}'.");
        }

        $this->args['item_type'] = $type;

        $this->setReady();

        return $this;
    }

    /**
     * Shorthand method to set the name and type for an object item.
     *
     * @param  string $name The item name.
     * @param  array  $args Optional additional arguments for the item (e.g. 'show_in_rest' => true).
     * 
     * @return self
     */
    public function object(string $name, array $args = []): self {
        $this->args = array_merge($this->args, $args);

        return $this->name($name)->type('object');
    }

    /**
     * Sets the type of value for the item.
     *
     * @param  string $type The value type (e.g. 'string', 'boolean', etc.).
     * 
     * @return self
     */
    public function type(string $type): self {
        if (!in_array($type, $this->types)) {
            $this->error = "Invalid type '{$type}' specified for item '{$this->name}'.";
            return $this;
        }

        $this->args['type'] = $type;
        $this->type = $type;

        $this->setReady();
        return $this;
    }

    /**
     * Sets the label for the item.
     *
     * @param  string $label The human-readable label for the item.
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
     * @param  mixed $value The default value for the item.
     * 
     * @return self
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
                throw new Exception("Invalid default value for type '{$type}' on '{$this->name}'");
            }
        }

        $this->args['default'] = $value;

        return $this;
    }

    /**
     * Sets whether the item should be exposed in the REST API.
     *
     * @param  bool $show Whether to show the item in the REST API.
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
     * @param  array $args An associative array of arguments to merge with the existing item arguments.
     * 
     * @return self
     */
    public function args(array $args): self {
        $this->args = array_merge($this->args, $args);

        $this->setReady();
        return $this;
    }

    /**
     * Sets the layout callback for the item, which can be used to customize how the item is rendered in the admin UI.
     *
     * @param  Closure $callback A closure that defines the layout for the item.
     * 
     * @return self
     */
    public function layout(Closure $callback): self {
        $this->layout = $callback;
        return $this;
    }

    /**
     * Retrieves the layout callback for the item, if one is set.
     *
     * @return Closure|null The layout callback, or null if none is set.
     */
    public function getLayout(): ?Closure {
        return $this->layout;
    }

    /***************************
     * Helpers
     ***************************/

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
     * Retrieves all sub-items that are repeatable children of the specified path.
     *
     * @param  string $path The dot-notated path to check for repeatable children (e.g. 'my_array').
     * 
     * @return array An array of sub-items that are repeatable children of the specified path.
     */
    public function getRepeatableChildren(string $path): array {
        return array_filter($this->subItems, function ($item) use ($path) {
            return str_starts_with($item->path, "{$path}.*.");
        });
    }
}