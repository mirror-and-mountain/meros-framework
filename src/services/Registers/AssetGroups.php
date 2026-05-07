<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\AssetGroup;

class AssetGroups extends Register {
    protected string $identifier = 'name';
    protected string $definition = AssetGroup::class;

    /**
     * Parses properties for the asset group's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'name'        => $props['name'] ?? '',
            'label'       => $props['label'] ?? '',
            'description' => $props['description'] ?? ''
        ];
    }
}