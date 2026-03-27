<?php 

namespace MM\Meros\App\Facades;

use Illuminate\Support\Facades\Facade;
use MM\Meros\App\FeatureRegistry;

class Registry extends Facade {
    protected static function getFacadeAccessor() {
        return FeatureRegistry::class;
    }
}