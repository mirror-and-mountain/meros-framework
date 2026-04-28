<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class Theme extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.theme';
    }
}