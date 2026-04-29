<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Tables extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.tables';
    }
}