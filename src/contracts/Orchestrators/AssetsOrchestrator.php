<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesAssets;

abstract class AssetsOrchestrator extends Orchestrator {
    use ProvidesAssets;
}