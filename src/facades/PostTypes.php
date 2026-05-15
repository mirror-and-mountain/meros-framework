<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class PostTypes extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.post_types';
    }
}