<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class SettingsFields extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.settings_fields';
    }
}