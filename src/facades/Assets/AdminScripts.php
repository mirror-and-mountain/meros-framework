<?php 

namespace MM\Meros\Facades\Assets;

use Illuminate\Support\Facades\Facade;

class AdminScripts extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.admin_scripts';
    }
}