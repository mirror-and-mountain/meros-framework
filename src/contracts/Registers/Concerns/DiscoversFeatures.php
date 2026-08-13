<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use Illuminate\Support\Facades\File;
use MM\Meros\Contracts\Providers\FeatureProvider;

trait DiscoversFeatures {
    use MakesFeatures;

    /**
     * Discovers features from the specified path. 
     * 
     * This method should be implemented by classes using 
     * this trait to define how features are discovered.
     *
     * @param string $path
     *
     * @return void
     */
    abstract public function discover(string $path = ''): void;

    /**
     * Retrieves the provider associated with this register.
     *
     * @return FeatureProvider|null
     */
    abstract protected function getProvider(): ?FeatureProvider;

    /**
     * Resolves a given path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string|null  $path The path to resolve. If null, the provider's default assets path will be used.
     * @param string       $defaultPath The default path to use if $path is null. This should be the provider's default assets path.
     * 
     * @return string|null The resolved path if it exists and is a directory, or null if not found.
     */
    final protected function resolvePath(?string $path, string $defaultPath): ?string {
        if ($path === null) {
            $path = $defaultPath;
        }

        if (File::exists($path) && File::isDirectory($path)) {
            return $path;
        }

        $providerBasePath  = $this->getProvider()->getPath();
        $potentialPath     = rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        return File::exists($potentialPath) && File::isDirectory($potentialPath) ? $potentialPath : null;
    }
}