<?php

namespace MM\Meros\Registers\Security;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Security\Role;
use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;
use MM\Meros\Facades\Security\Roles as RolesFacade;

class Roles extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(Role::class);
        $this->facade(RolesFacade::class);
        $this->identifierFormat('snake');
        add_action('init', [Role::class, 'applyBuiltInCapabilitySettings'], 20);
    }
}
