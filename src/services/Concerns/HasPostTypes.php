<?php

namespace MM\Meros\Services\Concerns;

use Closure;
use MM\Meros\Services\Contracts\PostType;
use MM\Meros\Services\Contracts\PostMeta;

use MM\Meros\Facades\PostTypes;
use MM\Meros\Facades\PostMetaDefinitions;

use MM\Meros\Services\Registers\PostTypes as PostTypesRegister;
use MM\Meros\Services\Registers\PostMetaDefinitions as PostMetaRegister;

trait HasPostTypes {
    /**
     * Retrieves a post type if a handle is provided or the post type register if no handle is provided.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return PostType|PostTypesRegister|null
     */
    protected function postTypes(string $handle = '', ?Closure $callback = null): PostType|PostTypesRegister|null {
        if (empty($handle)) {
            return PostTypes::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return PostTypes::get($handle, $this->resolveAuthority(), $callback); // return specific post type
        }
    }

    /**
     * Retrieves a post type if a handle is provided or the post type register if no handle is provided.
     * Alias of postTypes() for users who prefer snake_case method names.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return PostType|PostTypesRegister|null
     */
    protected function post_types(string $handle = '', ?Closure $callback = null): PostType|PostTypesRegister|null {
        return $this->postTypes($handle, $callback);
    }

    /**
     * Retrieves a post meta definition if a key is provided or the post meta definition register if no key is provided.
     *
     * @param string       $key
     * @param Closure|null $callback
     *
     * @return PostMeta|PostMetaRegister|null
      */
    protected function postMeta(string $key = '', ?Closure $callback = null): PostMeta|PostMetaRegister|null {
        if (empty($key)) {
            return PostMetaDefinitions::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return PostMetaDefinitions::get($key, $this->resolveAuthority(), $callback); // return specific post meta definition
        }
    }

    /**
     * Retrieves a post meta definition if a key is provided or the post meta definition register if no key is provided.
     * Alias of postMeta() for users who prefer snake_case method names.
     *
     * @param string       $key
     * @param Closure|null $callback
     *
     * @return PostMeta|PostMetaRegister|null
      */
    protected function post_meta(string $key = '', ?Closure $callback = null): PostMeta|PostMetaRegister|null {
        return $this->postMeta($key, $callback);
    }
}