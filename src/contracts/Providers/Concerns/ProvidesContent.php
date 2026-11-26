<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Registers\Content\PostTypes;
use MM\Meros\Contracts\Features\Content\Taxonomy;
use MM\Meros\Registers\Content\Taxonomies;

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
        return $this->resolveFeatureRequestFor(PostType::class, $name);
    }

    /**
     * Retrieves a taxonomy by handle or returns the taxonomies register.
     *
     * @param string $name Optional. The taxonomy handle to retrieve.
     * @return Taxonomy|Taxonomies|null The requested taxonomy or its register.
     */
    final protected function taxonomies(string $name = ''): Taxonomy|Taxonomies|null {
        return $this->resolveFeatureRequestFor(Taxonomy::class, $name);
    }
}