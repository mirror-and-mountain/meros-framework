<?php 

namespace MM\Meros\Facades\Components;

use Illuminate\Support\Facades\Facade;

class FieldGroups extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.field_groups';
    }
}