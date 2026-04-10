<?php 

namespace MM\Meros\App\Features\Concerns;

use Exception;

use MM\Meros\App\Features\ObjectBuilder;
use MM\Meros\App\Contracts\ObjectRegistrar;

trait HasObjectBuilder {
    public ?ObjectRegistrar $parent = null;

    public ?string $path = null;
    public array   $subItems = [];

    /**
     * Returns a new ObjectBuilder instance scoped to the current feature and optional path.
     *
     * @return ObjectBuilder A new ObjectBuilder instance for building nested settings or schema.
     * @throws Exception If the builder is used on a feature that is not of type 'object' or 'array', or is not root.
     */
    public function builder(): ObjectBuilder {
        $type = $this->args['type'] ?? null;

        // Must be root
        if ($this->parent !== null) {
            throw new Exception('Builder can only be used on root settings.');
        }

        // Must be object or array
        if (!in_array($type, ['object', 'array'])) {
            throw new Exception("Builder can only be used on 'object' or 'array' types. '{$type}' given.");
        }

        // Ensure valid name
        if (empty($this->optionName)) {
            throw new Exception('Builder requires a valid root setting name.');
        }

        // Always reset path from source of truth (optionName)
        $basePath = $this->optionName;

        // Array = repeatable → append * (but don't mutate original path permanently)
        $builderPath = $type === 'array'
            ? "{$basePath}.*"
            : $basePath;

        // Ensure schema knows this is an object array
        if ($type === 'array') {
            $this->args['item_type'] = $this->args['item_type'] ?? 'object';
        }

        return app(ObjectBuilder::class, [
            'root' => $this,
            'path' => $builderPath
        ]);
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
     * @param  ObjectRegistrar $parent The parent object definition to set.
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function parent(ObjectRegistrar $parent): self {
        $this->parent = $parent;

        return $this;
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

        // Only include default if explicitly set AND not null
        if (array_key_exists('default', $this->args) && $this->args['default'] !== null) {
            $schema['default'] = $this->args['default'];
        }

        // Title
        if (!empty($this->args['label'])) {
            $schema['title'] = $this->args['label'];
        }

        // Description
        if (!empty($this->args['description'])) {
            $schema['description'] = $this->args['description'];
        }

        // OBJECT
        if ($type === 'object') {
            if (!empty($this->subItems)) {
                $schema['properties'] = [];

                foreach ($this->subItems as $child) {
                    $schema['properties'][$child->optionName] = $child->toSchema();
                }
            }
        }

        // ARRAY
        if ($type === 'array') {
            $itemType = $this->args['item_type'] ?? 'string';

            $schema['items'] = [
                'type' => $itemType,
            ];

            // Array of objects (repeatable rows)
            if ($itemType === 'object') {
                if (!empty($this->subItems)) {
                    $schema['items']['properties'] = [];

                    foreach ($this->subItems as $child) {
                        $schema['items']['properties'][$child->optionName] = $child->toSchema();
                    }
                }
            }
        }

        return $schema;
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
                if ($child->optionName === $segment) {
                    $current = $child;
                    continue 2;
                }
            }
        }

        return $current;
    }
}