<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\PostType;

class PostTypes extends Register {
    protected string $identifier = 'handle';
    protected string $definition = PostType::class;
    protected array  $rejects    = ['register', 'multiple', 'makeFrom', 'makeFromCallback'];

    /**
     * Parses properties for the post type's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No additional parsing needed for post type properties; they are passed directly to the constructor.
    }
}