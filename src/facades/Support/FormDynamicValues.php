<?php 

namespace MM\Meros\Facades\Support;

use Illuminate\Support\Facades\Facade;

class FormDynamicValues extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.helpers.form_dynamic_values';
    }
}