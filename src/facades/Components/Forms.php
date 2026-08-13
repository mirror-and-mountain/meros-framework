<?php 

namespace MM\Meros\Facades\Components;

use Illuminate\Support\Facades\Facade;

class Forms extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.forms';
    }
}