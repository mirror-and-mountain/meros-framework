<?php 

namespace MM\Meros\Services\Facades;

use Illuminate\Support\Facades\Facade;

class Theme extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.theme';
    }
}