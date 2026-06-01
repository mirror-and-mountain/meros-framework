<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class FieldWrappers extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.field_wrappers';
    }
}