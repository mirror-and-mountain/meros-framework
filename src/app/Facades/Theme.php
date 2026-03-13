<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;
use MM\Meros\App\Services\ThemeManager;

class Theme extends Facade {
    protected static function getFacadeAccessor() {
        return ThemeManager::class;
    }
}