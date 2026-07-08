<?php

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class DynamicChoiceSources extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.dynamic_choice_sources';
    }
}
