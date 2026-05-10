<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
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

    use Concerns\HasViews, Concerns\HasRoutes, Concerns\HasLivewire;

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

    protected function beforeBoot(): void {
        // This method can be overridden by child classes to perform actions before the boot process
    }

    final public function boot(): void {
        $package = $this->instance;

        if (!$package->isEnabled()) {
            return;
        }

        $this->beforeBoot();

        // Register Livewire components
        $this->registerLivewireComponents($package);
        
        // Load views from the packages's views directory
        $this->registerViews($package);

        // Load routes from the package's routes directory
        $this->registerRoutes($package);

        $this->afterBoot();
    }

    protected function afterBoot(): void {
        // This method can be overridden by child classes to perform actions after the boot process
    }
}