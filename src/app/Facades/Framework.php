<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;

class Framework extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.framework';
    }
}