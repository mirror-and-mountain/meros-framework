<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

use MM\Meros\App\Package;
use MM\Meros\App\FeatureRegistry;
use MM\Meros\App\Facades\Registry;

use MM\Meros\App\Support\ClassInfo;

abstract class PackageServiceProvider extends ServiceProvider {
    /**
     * The fully qualified class name of the feature this service provider registers.
     *
     * @var string
     */
    protected string $serviceClass;

    final public function register(): void {
        $class = ClassInfo::get($this->serviceClass);
        
        if ($class->extends(Package::class)) { 
            $name = Str::headline($class->shortName);         
            $path = $class->path;
            $uri  = $class->uri;

            $this->app->singleton(
                $this->serviceClass,
                fn () => new $this->serviceClass($this->app->make(FeatureRegistry::class), $name, $path, $uri)
            );

            // Add package to the registry
            Registry::addPackage($this->app->make($this->serviceClass));
        }
    }

    final public function boot(): void {
        // Do nothing...
    }
}