<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;

trait Abstracts {
    abstract protected function attachInstance(FeatureDefinition $instance, FeatureProvider $provider): void;
    abstract protected function ensureCheckout(string $action = ''): void;
    abstract protected function getProvider(): ?FeatureProvider;

    abstract public function checkout(?FeatureProvider $provider = null): static;
    abstract public function checkin(): static;
    abstract public function isPrivate(): bool;
    abstract public function usesUniqueInstances(): bool;
    abstract public function getDefinition(): string;
    abstract public function get(string $name, ?FeatureProvider $provider = null): FeatureDefinition|null;
    abstract public function has(string $name, ?Feature $excludingFeature = null, ?FeatureProvider $provider = null): bool;
}