<?php 

namespace MM\Meros\Facades\Support;

use Illuminate\Support\Facades\Facade;

class Registers extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.helpers.registers';
    }
}