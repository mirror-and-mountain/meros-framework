<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Fields extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.fields';
    }
}