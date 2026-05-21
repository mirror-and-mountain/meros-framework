<?php 

namespace MM\Meros\Facades;

use Illuminate\Support\Facades\Facade;

class FormStyles extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.form_styles';
    }
}