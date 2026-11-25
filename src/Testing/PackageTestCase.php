<?php

namespace MM\Meros\Testing;

use MM\Meros\App\Package;
use MM\Meros\Facades\Packages;
use MM\Meros\Foundations\Application;
use ReflectionClass;

/**
 * Discovers a package in real WordPress tests with configurable enablement and access to its registered instance.
 */
abstract class PackageTestCase extends WordPressTestCase
{
    abstract protected function packageClass(): string;
    abstract protected function packageHandle(): string;

    protected function packageEnabled(): bool
    {
        return true;
    }

    protected function packageRoots(): array
    {
        $root = dirname((new ReflectionClass($this->packageClass()))->getFileName(), 2);
        $composer = json_decode($this->files->get($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        return [$composer['name'] => $root];
    }

    protected function createApplication(array $providers = []): Application
    {
        update_option('meros_package_settings', [$this->packageHandle() . '_enabled' => $this->packageEnabled()]);
        return parent::createApplication($providers);
    }

    protected function package(): Package
    {
        $package = Packages::all()->first(fn ($package) => $package instanceof ($this->packageClass()));
        $this->assertInstanceOf($this->packageClass(), $package);
        return $package;
    }
}
