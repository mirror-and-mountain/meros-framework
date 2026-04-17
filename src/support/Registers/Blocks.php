<?php 

namespace MM\Meros\App\Support\Registrars;

use MM\Meros\Services\Block;

class Blocks extends Register {
    protected string $identifier = 'name';
    protected string $itemClass  = Block::class;

    /**
     * Parses properties for the block's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];

        return [
            'name' => $props['name'] ?? '',
            'path' => $props['path'] ?? '',
            'args' => [
                'api_version'           => $args['api_version'] ?? 1,
                'title'                 => $args['title'] ?? '',
                'description'           => $args['description'] ?? '',
                'textdomain'            => $args['textdomain'] ?? '',
                'render_callback'       => $args['render_callback'] ?? null,
                'category'              => $args['category'] ?? '',
                'icon'                  => $args['icon'] ?? '',
                'keywords'              => $args['keywords'] ?? [],
                'parent'                => $args['parent'] ?? [],
                'ancestor'              => $args['ancestor'] ?? [],
                'allowed_blocks'        => $args['allowed_blocks'] ?? [],
                'provides_context'      => $args['provides_context'] ?? [],
                'uses_context'          => $args['uses_context'] ?? [],
                'supports'              => $args['supports'] ?? [],
                'attributes'            => $args['attributes'] ?? [],
                'styles'                => $args['style_variations'] ?? [],
                'variations'            => $args['variations'] ?? [],
                'selectors'             => $args['selectors'] ?? [],
                'variation_callback'    => $args['variation_callback'] ?? null,
                'script_handles'        => $args['script_handles'] ?? [],
                'style_handles'         => $args['style_handles'] ?? [],
                'editor_script_handles' => $args['editor_script_handles'] ?? [],
                'editor_style_handles'  => $args['editor_style_handles'] ?? [],
                'view_script_handles'   => $args['view_script_handles'] ?? [],
                'view_style_handles'    => $args['view_style_handles'] ?? [],
            ]
        ];
    }
}