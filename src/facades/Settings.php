<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Settings extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.settings';
    }
}