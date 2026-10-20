<?php 

namespace MM\Meros\Facades\Automation;

use Illuminate\Support\Facades\Facade;

class FlowActions extends Facade {
    protected static function getFacadeAccessor() {
        return 'meros.registers.flow_actions';
    }
}