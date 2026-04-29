<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class FieldStyles extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.field_styles';
    }
}