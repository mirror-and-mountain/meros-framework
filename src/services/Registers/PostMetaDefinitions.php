<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\PostMeta;

class PostMetaDefinitions extends Register {
    protected string $identifier = 'name';
    protected string $definition = PostMeta::class;
    protected array  $rejects    = ['register', 'multiple', 'makeFrom', 'makeFromCallback'];

    /**
     * Parses properties for the post meta's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No additional parsing needed for post meta properties; they are passed directly to the constructor.
    }
}