<?php 

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

use MM\Meros\App\Services\Theme\Package;
use MM\Meros\App\Facades\Theme;

use MM\Meros\App\Helpers\ClassInfo;

abstract class PackageServiceProvider extends ServiceProvider {
    /**
     * The fully qualified class name of the feature this service provider registers.
     *
     * @var string
     */
    protected string $serviceClass;

    /**
     * Registers the feature by instantiating it and adding it to the theme manager's
     * registered features.
     *
     * @return void
     */
    final public function register(): void {
        $class = ClassInfo::get($this->serviceClass);
        
        if ($class->extends(Package::class)) { 
            $name = Str::headline($class->shortName);         
            $path = $class->path;
            $uri  = $class->uri;

            $this->app->singleton(
                $this->serviceClass,
                fn () => new $this->serviceClass($name, $path, $uri)
            );

            $this->app->tag($this->serviceClass, 'meros.theme.package');

            $service = $this->app->make($this->serviceClass);
            $slug    = $service->getSlug();

            Theme::bindPackage($slug, $service);
            $this->afterRegister();
        }
    }

    protected function afterRegister(): void {
        // Additional logic to run after the feature has been registered can be added here.
    }

    final public function boot(): void {
        $this->app->booted(function () {
            $this->afterBoot();
        });
    }

    protected function afterBoot(): void {
        // Additional logic to run after all providers have booted can be added here.
    }
}