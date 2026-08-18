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
    final protected function resolveDirectoryPath(?string $path = null, string $defaultPath): ?string {
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
     * Checks whether the given file path has one of the specified extensions.
     *
     * @param string $path
     * @param array  $extensions
     * @param bool   $throwError Whether to throw an exception if the file does not have a valid extension.
     *
     * @return bool
     */
    final protected function fileHasExtensions(string $path, array $extensions, bool $throwError = false): bool {
        if (!$this->pathIsFile($path)) {
            if ($throwError) {
                throw new \InvalidArgumentException("The provided path '{$path}' is not a valid file.");
            }
            
            return false;
        }
    
        $extension = File::extension($path);
        $hasExtension = in_array($extension, $extensions);

        if (!$hasExtension && $throwError) {
            throw new \InvalidArgumentException("The provided path '{$path}' does not have a valid extension.");
        }

        return $hasExtension;
    }

    /**
     * Checks if the given directory contains at least one file with one of the specified extensions.
     *
     * @param string $directory
     * @param array  $extensions
     *
     * @return boolean
     */
    final protected function directoryHasFileWithExtensions(string $directory, array $extensions): bool {
        if (!$this->pathIsDirectory($directory)) {
            return false;
        }

        $files = File::files($directory);
        foreach ($files as $file) {
            if (in_array($file->getExtension(), $extensions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the first file in the given directory that has one of the specified extensions.
     *
     * @param string $directory
     * @param array  $extensions
     *
     * @return string|null The path of the first matching file, or null if none found.
     */
    final protected function getFirstFileInDirectoryWithExtensions(string $directory, array $extensions): ?string {
        if (!$this->pathIsDirectory($directory)) {
            return null;
        }

        $files = File::files($directory);
        foreach ($files as $file) {
            if (in_array($file->getExtension(), $extensions)) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Checks if a given directory contains at least one subdirectory, or a specific subdirectory if provided.
     *
     * @param string $directory
     * @param string $subdirectory
     *
     * @return boolean
     */
    final protected function directoryHasSubdirectory(string $directory, string $subdirectory = ''): bool {
        if (!$this->pathIsDirectory($directory)) {
            return false;
        }

        if ($subdirectory === '') {
            return File::directories($directory) !== [];
        }

        $subdirectoryPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($subdirectory, DIRECTORY_SEPARATOR);
        return $this->pathIsDirectory($subdirectoryPath);
    }

    /**
     * Retrieves the first subdirectory within a given directory, or null if none exist.
     *
     * @param string $directory
     *
     * @return string|null
     */
    final protected function getFirstSubdirectory(string $directory): ?string {
        if (!$this->pathIsDirectory($directory)) {
            return null;
        }

        $subdirectories = File::directories($directory);
        return $subdirectories[0] ?? null;
    }

    /**
     * Retrieves all subdirectories within a given directory, or an empty array if none exist.
     *
     * @param string $directory
     *
     * @return array
     */
    final protected function getSubdirectories(string $directory): array {
        if (!$this->pathIsDirectory($directory)) {
            return [];
        }

        return File::directories($directory);
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