<?php

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Concerns\IsSwitchable;
use MM\Meros\Contracts\Concerns\ResolvesFeatureRequests;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Registers\Admin\SettingsContainers;

abstract class Orchestrator {
    /**
     * The feature provider instance used by the orchestrator.
     *
     * @var FeatureProvider
     */
    private FeatureProvider $provider;

    use ResolvesFeatureRequests, IsSwitchable;

    /**
     * Constructs a new instance of the orchestrator with the specified feature provider.
     *
     * @param FeatureProvider $provider The feature provider to be used by the orchestrator.
     */
    private function __construct(FeatureProvider $provider) {
        $this->provider = $provider;
        $this->configure();
    }

    /**
     * Creates a new instance of the orchestrator with the specified feature provider.
     *
     * @param FeatureProvider $provider The feature provider to be used by the orchestrator.
     *
     * @return static A new instance of the orchestrator.
     */
    final public static function create(FeatureProvider $provider): static {
        return new static($provider);
    }

    /**
     * Retrieves the feature provider associated with the orchestrator.
     *
     * @return FeatureProvider
     */
    final public function getProvider(): FeatureProvider {
        return $this->provider;
    }

    /**
     * Resolves the settings container used to register the switch setting for the orchestrator, if it is switchable.
     *
     * @param SettingsContainers $register
     *
     * @return SettingsContainer
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        return $this->getProvider()->resolveSettingsContainer($register);
    }

    /**
     * This method is intended to be overridden by subclasses to provide additional configuration logic.
     *
     * @return void
     */
    protected function configure(): void {
        // This method can be overridden by subclasses to provide additional configuration logic.
    }

    /**
     * Returns the unique identifier for the orchestrator, which is derived from the class name.
     *
     * @param string $format Ignored.
     *
     * @return string
     */
    final public function getIdentifier(string $format = 'default'): string {
        return Str::snake(class_basename(static::class));
    }

    /**
     * Retrieves a preference value from the associated feature provider.
     *
     * @param string  $key
     * @param boolean $fullPath
     *
     * @return mixed
     */
    final public function getPreference(string $key, bool $fullPath = true): mixed {
        return $this->getProvider()->getPreference($key, $fullPath);
    }

    /**
     * Retrieves the handle of the associated feature provider.
     *
     * @param boolean $slug
     *
     * @return string
     */
    final public function getHandle(bool $slug = false): string {
        return $this->getProvider()->getHandle($slug);
    }

    /**
     * Determines whether the given string looks like a fully qualified classname.
     *
     * @param string $string
     *
     * @return boolean
     */
    final protected function looksLikeClass(string $string): bool {
        return Str::contains($string, '\\');
    }

    /**
     * Retrieves the name of the associated feature provider.
     *
     * @return string
     */
    final public function getName(): string {
        return $this->getProvider()->getName();
    }

    /**
     * This method is intended to be overridden by subclasses to provide logic that runs when the orchestrator is enabled.
     *
     * @return void
     */
    protected function whenEnabled(): void {
        // This method can be overridden by subclasses to provide logic that runs when the orchestrator is enabled.
    }

    /**
     * This method is intended to be overridden by subclasses to provide logic that runs when the orchestrator is disabled.
     *
     * @return void
     */
    protected function whenDisabled(): void {
        // This method can be overridden by subclasses to provide logic that runs when the orchestrator is disabled.
    }
}