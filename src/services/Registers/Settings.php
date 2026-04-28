<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Admin\Setting;
use MM\Meros\Services\Contracts\Register;

class Settings extends Register {
    protected string $identifier = 'name';
    protected string $definition = Setting::class;

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
            'group' => $props['group'] ?? '',
            'name'  => $props['name'] ?? '',
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