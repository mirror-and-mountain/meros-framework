<?php

namespace MM\Meros\Contracts;

use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Providers\FeatureProvider;

abstract class MerosServiceProvider extends ServiceProvider {
    /**
     * Registers views for the item (theme or package) using the path specified in preferences.
     * 
     * @param FeatureProvider $provider The provider instance (theme or package) to get preferences from.
     * @param string $handle  Optional handle to use for the view namespace. If not provided, the provider's handle will be used.
     *
     * @return void
     */
   final protected function registerViews(FeatureProvider $provider, string $handle = ''): void {
      $viewsPath = $provider->getPreference('views_path');
      $this->loadViewsFrom($viewsPath, !empty($handle) ? $handle : $provider->getHandle());

      // Register the item's components directory for anonymous components
      Blade::anonymousComponentPath($viewsPath . '/components');
   }

   /**
    * Registers routes for the item (theme or package) using the path specified in preferences.
    * 
    * @param FeatureProvider $provider The provider instance (theme or package) to get preferences from.
    *
    * @return void
    */
   final protected function registerRoutes(FeatureProvider $provider): void {
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

   /**
    * Registers Livewire components for the item (theme or package) using the specified preferences.
    *
    * @param FeatureProvider $provider
    * @param string          $handle
    * @param string          $namespace
    * @param string          $path
    * @param string          $viewsPath
    *
    * @return void
    */
   final protected function registerLivewireComponents(
        FeatureProvider $provider, 
        string $handle = '',
        string $namespace = '',
        string $path = '',
        string $viewsPath = ''
    ): void {
      Livewire::addNamespace(
         namespace: !empty($handle) ? $handle : $provider->getHandle(),
         classNamespace: !empty($namespace) ? $namespace : $provider->getPreference('livewire_namespace'),
         classPath: !empty($path) ? $path : $provider->getPreference('livewire_path'),
         classViewPath: !empty($viewsPath) ? $viewsPath : $provider->getPreference('livewire_views_path')
      );
   }
}