<?php 

namespace MM\Meros\Facades\Admin;

use Illuminate\Support\Facades\Facade;

class SettingsSections extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.settings_sections';
    }
}