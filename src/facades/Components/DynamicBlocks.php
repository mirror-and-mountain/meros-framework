<?php 

namespace MM\Meros\Facades\Components;

use Illuminate\Support\Facades\Facade;

class DynamicBlocks extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.dynamic_blocks';
    }
}