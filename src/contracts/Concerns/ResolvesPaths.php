<?php

namespace MM\Meros\Contracts\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use MM\Meros\Contracts\Providers\FeatureProvider;

trait ResolvesPaths {
    /**
     * Resolves the provider associated with the implementing class.
     *
     * @return FeatureProvider
     */
    abstract public function getProvider(): FeatureProvider;

    /**
     * Resolves a given path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string|null  $path The path to resolve. If null, the provider's default assets path will be used.
     * @param string       $defaultPath The default path to use if $path is null. This should be the provider's default assets path.
     * @param bool         $directory Whether to check for a directory (true) or a file (false).
     * 
     * @return string|null The resolved path if it exists, or null if not found.
     */
    final protected function resolvePath(?string $path, string $defaultPath, bool $directory = false): ?string {
        if ($directory) {
            return $this->resolveDirectoryPath($path, $defaultPath);
        }

        return $this->resolveFilePath($path, $defaultPath);
    }

    /**
     * Resolves a given path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string|null  $path The path to resolve. If null, the provider's default assets path will be used.
     * @param string       $defaultPath The default path to use if $path is null. This should be the provider's default assets path.
     * 
     * @return string|null The resolved path if it exists and is a directory, or null if not found.
     */
    final protected function resolveDirectoryPath(?string $path, string $defaultPath): ?string {
        if ($path === null) {
            $path = $defaultPath;
        }

        if ($this->pathIsDirectory($path)) {
            return $path;
        }

        $providerBasePath  = $this->getProvider()->getPath();
        $potentialPath     = rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        return $this->pathIsDirectory($potentialPath) ? $potentialPath : null;
    }

    /**
     * Resolves a given file path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string|null  $path The file path to resolve. If null, the provider's default assets path will be used.
     * @param string       $defaultPath The default file path to use if $path is null. This should be the provider's default assets path.
     * 
     * @return string|null The resolved file path if it exists and is a file, or null if not found.
     */
    final protected function resolveFilePath(?string $path, string $defaultPath): ?string {
        if ($path === null) {
            $path = $defaultPath;
        }

        if ($this->pathIsFile($path)) {
            return $path;
        }

        $providerBasePath  = $this->getProvider()->getPath();
        $potentialPath     = rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        return $this->pathIsFile($potentialPath) ? $potentialPath : null;
    }

    /**
     * Checks if a given path is a directory.
     *
     * @param string $path The path to check.
     * 
     * @return bool True if the path is a directory, false otherwise.
     */
    final protected function pathIsDirectory(string $path): bool {
        return File::exists($path) && File::isDirectory($path);
    }

    /**
     * Checks if a given path is a file.
     *
     * @param string $path The path to check.
     * 
     * @return bool True if the path is a file, false otherwise.
     */
    final protected function pathIsFile(string $path): bool {
        return File::exists($path) && File::isFile($path);
    }

    /**
     * Checks if a given path appears to be an absolute path based on the provider's base path.
     *
     * @param string $path
     *
     * @return boolean
     */
    final protected function pathLooksAbsolute(string $path): bool {
        $providerBasePath = $this->getProvider()->getPath();
        return Str::startsWith($path, $providerBasePath);
    }

    /**
     * Converts a given file path to a URI based on the provider's base path and URI.
     *
     * @param string $path The file path to convert.
     * 
     * @return string The corresponding URI.
     */
    final protected function convertPathToUri(string $path): string {
        $providerBasePath = $this->getProvider()->getPath();
        $relativePath = Str::after($path, $providerBasePath);

        $providerUri = $this->getProvider()->getUri();
        return rtrim($providerUri, '/') . '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
    }

    /**
     * Converts a given URI to a file path based on the provider's base path and URI.
     *
     * @param string $uri The URI to convert.
     * 
     * @return string The corresponding file path.
     */
    final protected function convertUriToPath(string $uri): string {
        $providerUri = $this->getProvider()->getUri();
        $relativePath = Str::after($uri, $providerUri);

        $providerBasePath = $this->getProvider()->getPath();
        return rtrim($providerBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    }

    /**
     * Generates a version string for the asset based on its file's last modified timestamp.
     *
     * @param string $path The file path of the asset.
     * 
     * @return string The version string, or an empty string if the file does not exist.
     */
    final protected function generateVersionFromPath(string $path): string {
        if ($this->pathIsFile($path)) {
            return (string) File::lastModified($path);
        }

        return '';
    }
}