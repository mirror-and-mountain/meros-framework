<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Context extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.context';
    }
}