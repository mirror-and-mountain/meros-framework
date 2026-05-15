<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\PostMeta;

class PostMetaDefinitions extends Register {
    protected string $identifier = 'key';
    protected string $definition = PostMeta::class;
    protected array  $rejects    = ['multiple'];

    /**
     * Parses properties for the post meta's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];

        return [
            'key'  => $props['key'] ?? '',
            'args' => [
                'type'              => $args['type'] ?? 'string',
                'default'           => $args['default'] ?? null,
                'label'             => $args['label'] ?? '',
                'description'       => $args['description'] ?? '',
                'show_in_rest'      => $args['show_in_rest'] ?? false,
                'sanitize_callback' => $args['sanitize_callback'] ?? null,
                'auth_callback'     => $args['auth_callback'] ?? null,
                'single'            => $args['single'] ?? true,
            ],
        ];
    }
}