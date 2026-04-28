<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Assets extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.assets';
    }
}