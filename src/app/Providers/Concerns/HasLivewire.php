<?php 

namespace MM\Meros\App\Providers\Concerns;

use Livewire\Livewire;
use MM\Meros\Services\Contracts\FeatureProvider;

trait HasLivewire {
    /**
     * Registers Livewire components for the item (theme or package) using the path specified in preferences.
     * 
     * @param FeatureProvider $provider The provider instance (theme or package) to get preferences from.
     * @param string $handle  Optional handle to use for the Livewire namespace. If not provided, the provider's handle will be used.
     *
     * @return void
     */
    protected function registerLivewireComponents(FeatureProvider $provider, string $handle = ''): void {
        Livewire::addNamespace(
            namespace: !empty($handle) ? $handle : $provider->getHandle(),
            classNamespace: $provider->getPreference('livewire_namespace'),
            classPath: $provider->getPreference('livewire_path'),
            classViewPath: $provider->getPreference('livewire_views_path')
        );
    }
}