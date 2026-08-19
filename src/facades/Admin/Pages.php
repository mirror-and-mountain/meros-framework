<?php 

namespace MM\Meros\Facades\Admin;

use Illuminate\Support\Facades\Facade;

class Pages extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.pages';
    }
}