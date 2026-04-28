<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Blocks extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.blocks';
    }
}