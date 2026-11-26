<?php

namespace MM\Meros\Facades\Content;

use Illuminate\Support\Facades\Facade;

class Taxonomies extends Facade {
    /**
     * Retrieves the taxonomy register's service container alias.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'meros.registers.taxonomies';
    }
}
