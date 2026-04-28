<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Admin\SettingsSection;
use MM\Meros\Services\Contracts\Register;

class SettingsSections extends Register {
    protected string $identifier = 'id';
    protected string $definition = SettingsSection::class;

    /**
     * Parses properties for the settings section's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'id'       => $props['id'] ?? '',
            'title'    => $props['title'] ?? '',
            'pageSlug' => $props['page_slug'] ?? '',
            'args'     => $props['args'] ?? [],
            'callback' => $props['callback'] ?? null
        ];
    }
}