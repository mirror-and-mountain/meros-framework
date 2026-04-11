<?php

namespace MM\Meros\App\Support;

use Exception;
use Illuminate\Support\Str;
use MM\Meros\App\Contracts\DataRegistrar;

class DataBuilder {
    protected ?object $current = null;

    public function __construct(
        protected DataRegistrar $root,
        protected string $path,
        protected bool   $isArray = false
    ) {}

    /**
     * Generates the full dot-notated path for a given sub-item, based on the current builder's path.
     *
     * @param  string|null $append Optional additional segment to append to the current path.
     * 
     * @return string The full dot-notated path for the sub-item.
     */
    protected function fullPath(?string $append = null): string {
        return $append
            ? "{$this->path}.{$append}"
            : $this->path;
    }

    /**
     * Internal helper method to add a sub-item of a specific type to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  string $type The type of the item (e.g. 'string', 'integer', 'object').
     * 
     * @return object The newly created item definition.
     */
    protected function item(string $name, string $type = ''): object {
        $name = Str::snake($name);

        $item = $this->root->addSubItem(
            $this->fullPath($name),
            $name,
            $type
        );

        $this->current = $item;

        return $item;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Adds a string item to the current object definition.
     *
     * @param  string $name The name of the item.
     * 
     * @return self The newly created item definition.
     */
    public function string(string $name): self {
        $this->item($name, 'string');
        return $this;
    }

    /**
     * Adds a boolean item to the current object definition.
     *
     * @param  string $name The name of the item.
     * 
     * @return self The newly created item definition.
     */
    public function boolean(string $name): self {
        $this->item($name, 'boolean');
        return $this;
    }

    /**
     * Adds an integer item to the current object definition.
     *
     * @param  string $name The name of the item.
     * 
     * @return self The newly created item definition.
     */
    public function integer(string $name): self {
        $this->item($name, 'integer');
        return $this;
    }

    /**
     * Adds a number item to the current object definition.
     *
     * @param  string $name The name of the item.
     * 
     * @return self The newly created item definition.
     */
    public function number(string $name): self {
        $this->item($name, 'number');
        return $this;
    }

    /**
     * Adds an array item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  array $args Optional additional arguments for the array definition (e.g. 'item_type' => 'string').
     * 
     * @return self The newly created item definition.
     */
    public function array(string $name, array $args = []): self {
        $args['item_type'] = $args['item_type'] ?? 'string';

        $setting = $this->item($name, 'array');

        if (!empty($args)) {
            $setting->args($args);
        }

        $builder = app(self::class, [
            'root'    => $this->root,
            'path'    => $this->fullPath($name) . '.*',
            'isArray' => true
        ]);

        $builder->current = $setting;

        return $builder;
    }

    /**
     * Adds an object item to the current object definition and returns a new scoped builder.
     *
     * @param  string $name The name of the item.
     * @param  callable|null $callback Optional callback that receives a new DataBuilder instance scoped to the group for defining nested items.
     * 
     * @return self A new DataBuilder instance scoped to the newly created object item.
     */
    public function object(string $name, ?callable $callback = null): self {
        $this->item($name, 'object');

        $builder = app(self::class, [
            'root'    => $this->root,
            'path'    => $this->fullPath($name),
            'isArray' => false
        ]);

        if ($callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Defines the structure of a repeatable array row (array of objects).
     *
     * @param callable|null $callback
     * @return self
     */
    public function row(?callable $callback = null): DataBuilder {
        // Ensure we're working on an array
        if (($this->current->args['type'] ?? null) !== 'array') {
            throw new \Exception("row() can only be used on array settings.");
        }

        // Force item_type to object
        if ($this->isArray && ($this->current->args['item_type'] ?? null) !== 'object') {
            $this->current->args['item_type'] = 'object';
        }

        $builder = app(self::class, [
            'root'    => $this->root,
            'path'    => $this->path,
            'isArray' => true
        ]);

        $builder->current = $this->current;

        if ($callback) {
            $callback($builder);
        }

        return $builder;
    }

    /*******************
     * Utility methods
     *******************/

    /**
     * Returns whether the current builder is defining an array of items.
     *
     * @return bool True if the current builder is for an array, false otherwise.
     */
    public function isArray(): bool {
        return $this->isArray;
    }

    /**
     * Returns the root object definition instance that the builder is operating on.
     *
     * @return object The root object definition instance.
     */
    public function get(): object {
        return $this->root;
    }
    /**
     * Proxy method calls to the current setting instance when applicable.
     *
     * This allows chaining methods like ->default(), ->label(), etc.
     * after builder calls such as ->array() or ->object().
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed {
        if ($this->current && method_exists($this->current, $method)) {
            try {
                $this->current->{$method}(...$arguments);
            } catch (\Throwable $e) {
                throw new Exception(
                    "Error calling '{$method}' on setting '{$this->current->optionName}': " . $e->getMessage(),
                    0,
                    $e
                );
            }

            // If the underlying method returns the setting, keep fluent builder chain
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist on DataBuilder.");
    }
}