<?php 

namespace MM\Meros\Facades\Assets;

use Illuminate\Support\Facades\Facade;

class AssetGroups extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.asset_groups';
    }
}