<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Admin\SettingsField;

class SettingsFields extends Register {
    protected string $identifier = 'id';
    protected string $itemClass  = SettingsField::class;
    protected array  $rejects    = ['multiple', 'makeFrom', 'makeFromCallback'];

    /**
     * Parses properties for the setting field's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        $args = $props['args'] ?? [];

        return [
            'id'       => $props['id'] ?? '',
            'title'    => $props['title'] ?? '',
            'callback' => $props['callback'] ?? null,
            'page'     => $props['page'] ?? '',
            'section'  => $props['section'] ?? 'default',
            'args'     => [
                'label_for' => $args['label_for'] ?? null,
                'class'     => $args['class'] ?? null,
            ]
        ];
    }
}