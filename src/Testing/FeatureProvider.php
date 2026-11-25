<?php

namespace MM\Meros\Testing;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Providers\FeatureProvider as FeatureProviderContract;
use MM\Meros\Registers\Admin\SettingsContainers;

/** Lightweight provider for testing feature contracts through their public creation APIs. */
final class FeatureProvider implements FeatureProviderContract
{
    public function __construct(private string $path, private string $handle = 'test_provider') {}

    public function get(): static { return $this; }
    public function getHandle(bool $slug = false): string { return $slug ? str_replace('_', '-', $this->handle) : $this->handle; }
    public function getName(): string { return 'Test Provider'; }
    public function getPath(string $subPath = ''): string { return rtrim($this->path, '/') . '/' . ltrim($subPath, '/'); }
    public function getUri(string $subPath = ''): string { return 'https://meros.test/provider/' . ltrim($subPath, '/'); }
    public function getPreference(string $key, bool $fullPath = true): mixed { return null; }

    public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer
    {
        return $register->get($this->handle . '_settings', $this)
            ?? $register->checkout($this)->make(fn (SettingsContainer $container) => $container->name($this->handle . '_settings'));
    }
}
