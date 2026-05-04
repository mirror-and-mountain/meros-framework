<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use MM\Meros\App\Package;
use MM\Meros\Facades\Packages;
use MM\Meros\Support\ClassInfo;

abstract class PackageServiceProvider extends ServiceProvider {
    /**
     * The fully qualified class name of the feature this service provider registers.
     *
     * @var string
     */
    protected string $serviceClass;

    /**
     * The instance of the package being registered.
     *
     * @var Package
     */
    private Package $instance;

    final public function register(): void {
        $class = ClassInfo::get($this->serviceClass);
        
        if ($class->extends(Package::class)) { 
            $name = Str::headline($class->shortName);         
            $path = $class->path;
            $uri  = $class->uri;

            $this->app->singleton($this->serviceClass, function () use ($name, $path, $uri) {
                return new $this->serviceClass($name, $path, $uri);
            });

            $this->instance = $this->app->make($this->serviceClass);
            Packages::checkout($this)->register($this->instance);
        }
    }

    final public function boot(): void {
        $package = $this->instance;

        if (!$package->isEnabled()) {
            return;
        }

        // Load views from the package's views directory
        $this->loadViewsFrom($package->getPreference('views_path'), $package->getHandle());

        // Load routes from the package's routes directory
        $routesPath = $package->getPreference('routes_path');
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