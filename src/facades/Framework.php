<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Framework extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.framework';
    }
}