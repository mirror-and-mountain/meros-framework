<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Admin\MenuPage;

class MenuPages extends Register {
    protected string $identifier = 'slug';
    protected string $definition = MenuPage::class;

    /**
     * List of supported operations for this register.
     *
     * @var array
     */
    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'get',
        'all',
        'attach'
    ];

    /**
     * Parses properties for the menu page's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'slug'        => $props['slug'] ?? '',
            'title'       => $props['title'] ?? '',
            'menu_title'  => $props['menu_title'] ?? '',
            'capability'  => $props['capability'] ?? 'manage_options',
            'icon'        => $props['icon'] ?? 'dashicons-admin-generic',
            'position'    => $props['position'] ?? null,
            'callback'    => $props['callback'] ?? null,
            'area'        => $props['area'] ?? 'menu',
        ];
    }
}