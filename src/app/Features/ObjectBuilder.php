<?php

namespace MM\Meros\App\Features;

use MM\Meros\App\Contracts\ObjectRegistrar;

class ObjectBuilder {
    public function __construct(
        protected ObjectRegistrar $root,
        protected string $path
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
     * @param  string $path The dot-notated path for the sub-item relative to the current builder's path (e.g. 'blocks.my-block').
     * @param  string $name The name of the item.
     * @param  string $type The type of the item (e.g. 'string', 'integer', 'object').
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    protected function item(string $name, string $type = '', mixed $default = null, array $args = []): object {
        return $this->root->addSubItem(
            $this->fullPath($name),
            $name,
            $type,
            $default,
            $args
        );
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Adds a string item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  mixed  $default Optional default value for the item.
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    public function string(string $name, mixed $default = null, array $args = []): object {
        return $this->item($name, 'string', $default, $args);
    }

    /**
     * Adds a boolean item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  mixed  $default Optional default value for the item.
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    public function boolean(string $name, mixed $default = null, array $args = []): object {
        return $this->item($name, 'boolean', $default, $args);
    }

    /**
     * Adds an integer item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  mixed  $default Optional default value for the item.
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    public function integer(string $name, mixed $default = null, array $args = []): object {
        return $this->item($name, 'integer', $default, $args);
    }

    /**
     * Adds a number item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  mixed  $default Optional default value for the item.
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    public function number(string $name, mixed $default = null, array $args = []): object {
        return $this->item($name, 'number', $default, $args);
    }

    /**
     * Adds an array item to the current object definition.
     *
     * @param  string $name The name of the item.
     * @param  mixed  $default Optional default value for the item.
     * @param  array  $args Optional additional arguments for the item.
     * 
     * @return object The newly created item definition.
     */
    public function array(string $name, mixed $default = null, array $args = []): object {
        return $this->item($name, 'array', $default, $args);
    }

    /**
     * Adds an object item to the current object definition and returns a new scoped builder.
     *
     * @param  string $name The name of the item.
     * @param  callable|null $callback Optional callback that receives a new ObjectBuilder instance scoped to the group for defining nested items.
     * 
     * @return self A new ObjectBuilder instance scoped to the newly created object item.
     */
    public function object(string $name, ?callable $callback = null): self {
        $item = $this->item($name, 'object');

        $builder = app(self::class, [
            'root' => $this->root,
            'path' => $this->fullPath($name)
        ]);

        if ($callback) {
            $callback($builder);
        }

        return $builder;
    }

    public function get(): object {
        return $this->root;
    }
}