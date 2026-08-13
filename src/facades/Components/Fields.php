<?php 

namespace MM\Meros\Facades\Components;

use Illuminate\Support\Facades\Facade;

class Fields extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.fields';
    }
}