<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class PostMetaDefinitions extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.post_meta_definitions';
    }
}