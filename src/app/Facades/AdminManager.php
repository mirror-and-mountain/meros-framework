<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;
use MM\Meros\App\Services\Theme\AdminManager as AdminManagerService;

class AdminManager extends Facade {
    protected static function getFacadeAccessor() {
        return AdminManagerService::class;
    }
}