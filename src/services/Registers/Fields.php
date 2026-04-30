<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Elements\Field;

class Fields extends Register {
    protected string $identifier = 'handle';
    protected string $definition = Field::class;

    /**
     * List of supported operations for this register.
     *
     * @var array
     */
    protected array  $supports = [
        'register',
        'makeFrom',
        'attach',
        'public',
        'get',
        'all',
    ];

    /**
     * Parses properties for the asset's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'handle'     => $props['handle'] ?? '',
            'id'         => $props['id'] ?? '',
            'name'       => $props['name'] ?? '',
            'label'      => $props['label'] ?? '',
            'help_text'  => $props['help_text'] ?? '',
            'default'    => $props['default'] ?? null,
            'value'      => $props['value'] ?? null,
            'required'   => $props['required'] ?? false,
            'disabled'   => $props['disabled'] ?? false,
            'classList'  => $props['classList'] ?? [],
            'width'      => $props['width'] ?? 'full',
            'style'      => $props['style'] ?? 'default',
        ];
    }
}