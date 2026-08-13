<?php 

namespace MM\Meros\Facades\Assets;

use Illuminate\Support\Facades\Facade;

class EditorStyles extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.editor_styles';
    }
}