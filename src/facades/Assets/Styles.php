<?php 

namespace MM\Meros\Facades\Assets;

use Illuminate\Support\Facades\Facade;

class Styles extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.styles';
    }
}