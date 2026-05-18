<?php

namespace MM\Meros\App\Models;

class MerosForm extends Post {
    public function newQuery() {
        return parent::newQuery()->where('post_type', 'meros-form');
    }
}