<?php 

namespace MM\Meros\Services\Facades;

use Illuminate\Support\Facades\Facade;

class Context extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.context';
    }
}