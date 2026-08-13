<?php 

namespace MM\Meros\Facades\Admin;

use Illuminate\Support\Facades\Facade;

class SettingsContainers extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.settings_containers';
    }
}