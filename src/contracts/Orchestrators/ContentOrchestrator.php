<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesContent;

abstract class ContentOrchestrator extends Orchestrator {
    use ProvidesContent;
}