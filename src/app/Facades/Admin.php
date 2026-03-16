<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;

class Admin extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.admin';
    }
}