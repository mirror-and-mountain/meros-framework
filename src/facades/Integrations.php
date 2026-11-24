<?php

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \MM\Meros\Contracts\Features\Integrations\Integration connect(string $name)
 */
class Integrations extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.integrations';
    }
}