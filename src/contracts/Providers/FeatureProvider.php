<?php

namespace MM\Meros\Contracts\Providers;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

interface FeatureProvider {
    /**
     * Returns the provider instance.
     *
     * @return static
     */
    public function get(): static;

    /**
     * Retrieves provider's handle.
     *
     * @return string
     */
    public function getHandle(): string;

    /**
     * Retrieves provider's name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the providers path, optionally appending a subpath.
     *
     * @param string $subPath
     *
     * @return string
     */
    public function getPath(string $subPath = ''): string;

    /**
     * Returns the providers URI, optionally appending a subpath.
     *
     * @param string $subPath
     *
     * @return string
     */
    public function getUri(string $subPath = ''): string;

    /**
     * Retrieves a preference value for the provider.
     *
     * @param string $key The preference key to retrieve.
     * @param bool   $fullPath Whether to return the full path (including the default path) or just the custom value set by the developer (only relavant for path preferences).
     *
     * @return mixed
     */
    public function getPreference(string $key, bool $fullPath = true): mixed;

    /**
     * Resolves the settings container for the provider.
     *
     * @param SettingsContainers $register The settings containers register.
     * 
     * @return SettingsContainer The resolved settings container.
     */
    public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer;
}