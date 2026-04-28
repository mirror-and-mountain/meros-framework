<?php 

namespace MM\Meros\Services\Registers\Concerns;

use Illuminate\Support\Facades\File;

trait Discovers {
    /**
     * Discovers features in the specified path and registers them.
     *
     * @param string|null $path The path to discover features from. If null, the provider's default path will be used.
     *
     * @return void
     */
    public function discover(?string $path = null): void {
        // To be implemented by the class using this trait
    }

    /**
     * Resolves a given path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string|null  $path The path to resolve. If null, the provider's default assets path will be used.
     * @param string       $defaultPath The default path to use if $path is null. This should be the provider's default assets path.
     * 
     * @return string|null The resolved path if it exists and is a directory, or null if not found.
     */
    private function resolvePath(?string $path, string $defaultPath): ?string {
        if ($path === null) {
            $path = $defaultPath;
        }

        if (File::exists($path) && File::isDirectory($path)) {
            return $path;
        }

        $providerBasePath  = $this->provider->getPath();
        $potentialPath     = rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        return File::exists($potentialPath) && File::isDirectory($potentialPath) ? $potentialPath : null;
    }
}