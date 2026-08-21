<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesAdminFeatures;

abstract class SettingsOrchestrator extends Orchestrator {
    use ProvidesAdminFeatures;
}