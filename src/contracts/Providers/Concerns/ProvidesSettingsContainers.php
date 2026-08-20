<?php 

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Registers\Admin\SettingsContainers;

trait ProvidesSettingsContainers {
    use Abstracts;

    /**
     * Retrieves a specific settings container by name or returns the SettingsContainers register checked out to the current provider.
     *
     * @param string $name The name of the settings container to retrieve.
     * 
     * @return SettingsContainer|SettingsContainers|null The requested settings container or the register.
     */
    final protected function settingsContainers(string $name = ''): SettingsContainer|SettingsContainers|null {
        return $this->resolveFeatureRequestFor(SettingsContainer::class, $name);
    }
}