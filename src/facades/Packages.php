<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Packages extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.packages';
    }
}