<?php 

namespace MM\Meros\App\Providers\Concerns;

use Illuminate\Support\Facades\Blade;
use MM\Meros\Services\Contracts\FeatureProvider;

trait HasViews {
    /**
     * Registers views for the item (theme or package) using the path specified in preferences.
     * 
     * @param FeatureProvider $provider The provider instance (theme or package) to get preferences from.
     * @param string $handle  Optional handle to use for the view namespace. If not provided, the provider's handle will be used.
     *
     * @return void
     */
    protected function registerViews(FeatureProvider $provider, string $handle = ''): void {
        $viewsPath = $provider->getPreference('views_path');
        $this->loadViewsFrom($viewsPath, !empty($handle) ? $handle : $provider->getHandle());

        // Register the item's components directory for anonymous components
        Blade::anonymousComponentPath($viewsPath . '/components');
    }
}