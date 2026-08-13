<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Registers\Content\PostTypes;

trait ProvidesContent {
    use Abstracts;

    /**
     * Retrieves a specific post type by handle or returns the post types register.
     *
     * @param string $name Optional. The name of the post type to retrieve.
     * 
     * @return PostType|PostTypes|null The requested post type or the post types register.
     */
    final protected function postTypes(string $name = ''): PostType|PostTypes|null {
        return $this->resolveRequestFor(PostType::class, $name);
    }
}