<?php 

namespace MM\Meros\App\Contracts;

use MM\Meros\App\Features\ObjectBuilder;

interface ObjectRegistrar {
    public function builder(): ObjectBuilder;

    /**
     * Defined based on usage for a Setting; however, params may be
     * substituted for other object types e.g:
     * 
     * PostMeta: object($postType = '', $metaKey = '', $args = [])
     *
     * @return self
     */
    public function object(string $group = '', string $name = '', array $args = []): self;

    /**
     * Defined based on usage for a Setting; however, params may be
     * substituted for other object types e.g:
     * 
     * PostMeta: addSubItem($path = 'some.path', $name = 'key', $type = '', $default = null, $args = [])
     *
     * @return self
     */
    public function addSubItem(string $path, string $name, string $type = '', mixed $default = null, array $args = []): object;

    /** Chainable method to set the dot-notated path for the object definition.
     *
     * @param  string|null $path The dot-notated path to set for the object definition (e.g. 'blocks.my-block').
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function path(?string $path): self;

    /**
     * Converts the object instance to a schema array for use in REST API registration.
     *
     * @return array
     */
    public function toSchema(): array;
}