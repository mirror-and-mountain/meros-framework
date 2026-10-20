<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesComponents;
use MM\Meros\Contracts\Providers\Concerns\ProvidesAutomation;

abstract class ComponentsOrchestrator extends Orchestrator {
    use ProvidesComponents, ProvidesAutomation;
}