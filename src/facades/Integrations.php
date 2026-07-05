<?php

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Integrations extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.integrations';
    }
}