<?php

namespace MM\Meros\Facades\Security;

use Illuminate\Support\Facades\Facade;

class Roles extends Facade {
    protected static function getFacadeAccessor(): string {
        return 'meros.registers.roles';
    }
}
