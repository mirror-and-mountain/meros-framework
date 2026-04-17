<?php 

namespace MM\Meros\App\Support\Registrars;

use MM\Meros\Services\Setting;

class Settings extends Register {
    protected string $identifier = 'name';
    protected string $itemClass  = Setting::class;

    /**
     * Parses properties for the setting's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];

        return [
            'group' => $props['option_group'] ?? '',
            'name'  => $props['option_name'] ?? '',
            'args'  => [
                'type'              => $args['type'] ?? 'string',
                'default'           => $args['default'] ?? null,
                'label'             => $args['label'] ?? '',
                'description'       => $args['description'] ?? '',
                'show_in_rest'      => $args['show_in_rest'] ?? false,
                'sanitize_callback' => $args['sanitize_callback'] ?? null
            ],      
        ];
    }
}