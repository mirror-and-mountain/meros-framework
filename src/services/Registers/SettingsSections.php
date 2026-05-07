<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Admin\SettingsSection;

class SettingsSections extends Register {
    protected string $identifier = 'id';
    protected string $definition = SettingsSection::class;
    protected array  $rejects    = ['multiple', 'public'];

    /**
     * Parses properties for the settings section's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'id'          => $props['id'] ?? '',
            'title'       => $props['title'] ?? '',
            'description' => $props['description'] ?? '',
            'args'        => $props['args'] ?? [],
            'callback'    => $props['callback'] ?? null
        ];
    }
}