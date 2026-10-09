<?php 

namespace MM\Meros\Facades\Support;

use Illuminate\Support\Facades\Facade;

class Ajax extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.helpers.ajax';
    }
}