<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class UserMetaDefinitions extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.user_meta_definitions';
    }
}
