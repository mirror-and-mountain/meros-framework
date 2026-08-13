<?php 

namespace MM\Meros\Facades\Admin;

use Illuminate\Support\Facades\Facade;

class MenuPages extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.menu_pages';
    }
}