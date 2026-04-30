<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class MenuPageTemplates extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.menu_page_templates';
    }
}