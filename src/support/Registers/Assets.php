<?php 

namespace MM\Meros\App\Support\Registrars;

use MM\Meros\Services\Asset;

class Assets extends Register {
    protected string $identifier = 'handle';
    protected string $itemClass  = Asset::class;

    /**
     * Parses properties for the asset's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'path'         => $props['path'] ?? '',
            'src'          => $props['src'] ?? '',
            'handle'       => $props['handle'] ?? '',
            'label'        => $props['label'] ?? '',
            'description'  => $props['description'] ?? '',
            'type'         => $props['type'] ?? '',
            'group'        => $props['group'] ?? '',
            'location'     => $props['location'] ?? '',
            'dependencies' => $props['dependencies'] ?? [],
            'version'      => $props['version'] ?? '',
            'inFooter'     => $props['inFooter'] ?? false,
        ];
    }
}