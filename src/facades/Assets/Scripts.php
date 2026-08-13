<?php 

namespace MM\Meros\Facades\Assets;

use Illuminate\Support\Facades\Facade;

class Scripts extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.scripts';
    }
}