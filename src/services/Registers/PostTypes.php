<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\PostType;

class PostTypes extends Register {
    protected string $identifier = 'handle';
    protected string $definition = PostType::class;
    protected array  $rejects    = ['multiple'];

    /**
     * Parses properties for the post type's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];
        $meta = is_array($props['meta'] ?? null) ? $props['meta'] : ($props['meta'] ?? []);

        return [
            'handle'        => $props['handle'] ?? '',
            'singularLabel' => $props['singular'] ?? '',
            'pluralLabel'   => $props['plural'] ?? '',
            'meta'          => $meta,
            'args'          => $args,
        ];
    }
}