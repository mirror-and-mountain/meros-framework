<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;

class Registry extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registry';
    }
}