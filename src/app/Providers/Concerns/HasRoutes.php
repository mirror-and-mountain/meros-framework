<?php 

namespace MM\Meros\App\Providers\Concerns;

use Illuminate\Support\Facades\File;
use MM\Meros\Services\Contracts\FeatureProvider;

trait HasRoutes {
    /**
     * Registers routes for the item (theme or package) using the path specified in preferences.
     * 
     * @param FeatureProvider $provider The provider instance (theme or package) to get preferences from.
     *
     * @return void
     */
    protected function registerRoutes(FeatureProvider $provider): void {
        $routesPath = $provider->getPreference('routes_path');

        if (File::exists($routesPath) && File::isDirectory($routesPath)) {
            $routeFiles = File::files($routesPath);
            foreach ($routeFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $this->loadRoutesFrom($file->getPathname());
                }
            }
        }
    }
}