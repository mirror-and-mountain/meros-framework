<?php 

namespace MM\Meros\Facades\Data;

use Illuminate\Support\Facades\Facade;

class PostMetaContainers extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.post_meta_containers';
    }
}