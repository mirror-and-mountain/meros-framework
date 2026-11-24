<?php

namespace MM\Meros\Facades\Security;

use Illuminate\Support\Facades\Facade;

class Capabilities extends Facade {
    protected static function getFacadeAccessor(): string {
        return 'meros.registers.capabilities';
    }
}
