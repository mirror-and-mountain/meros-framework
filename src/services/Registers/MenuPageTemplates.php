<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

class MenuPageTemplates extends Register {
    protected string $identifier = 'slug';
    protected string $definition = MenuPageTemplate::class;

    /**
     * Parses properties for the menu page's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // MenuPageTemplate classes handle their own property parsing
    }
}