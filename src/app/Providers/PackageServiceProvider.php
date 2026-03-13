<?php 

namespace MM\Meros\App\Services\Theme;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $theme = $this->app->make('meros.theme');
        $class = ClassInfo::get($this->serviceClass);
        
        if ($class->extends(Package::class)) {   
            $name = Str::headline($class->shortName);         
            $path = $class->path;
            $uri  = $class->uri;

            $this->app->singleton(
                $this->serviceClass,
                fn() => new $this->serviceClass(
                    $theme,
                    $name,
                    $path,
                    $uri
                )
            );

            $service = $this->app->make($this->serviceClass);
            $slug    = $service->getSlug();

            $theme->bindPackage($slug, $service);
            $this->afterRegister();
        }
    }

    protected function afterRegister(): void {
        // Additional logic to run after the feature has been registered can be added here.
    }

    public function boot(): void {
        // Logic to run during the booting of the service provider can be added here.
    }
}