<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Registers\Automation\FlowActions;

trait ProvidesAutomation {
    use Abstracts;

    /**
     * Retrieves a specific flow action by its name or returns the flow actions register.
     *
     * @param string $name Optional. The name of the registered flow action to retrieve.
     *
     * @return FlowAction|FlowActions|null The requested flow action or the flow actions register.
     */
    final protected function flowActions(string $name = ''): FlowAction|FlowActions|null {
        return $this->resolveFeatureRequestFor(FlowAction::class, $name);
    }

    /**
     * Retrieves a specific flow action by its name or returns the flow actions register.
     * Alias for flowActions() method.
     *
     * @param string $name Optional. The name of the registered flow action to retrieve.
     *
     * @return FlowAction|FlowActions|null The requested flow action or the flow actions register.
     */
    final protected function formActions(string $name = ''): FlowAction|FlowActions|null {
        return $this->resolveFeatureRequestFor(FlowAction::class, $name);
    }
}
