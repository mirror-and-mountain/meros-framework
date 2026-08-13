<?php 

namespace MM\Meros\Facades\Content;

use Illuminate\Support\Facades\Facade;

class PostTypes extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.post_types';
    }
}