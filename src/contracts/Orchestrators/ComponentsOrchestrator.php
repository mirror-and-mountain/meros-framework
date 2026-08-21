<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesComponents;

abstract class ComponentsOrchestrator extends Orchestrator {
    use ProvidesComponents;
}