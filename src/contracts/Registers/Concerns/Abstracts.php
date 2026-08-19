<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;

trait Abstracts {
    abstract protected function attachInstance(FeatureDefinition $instance, FeatureProvider $provider): void;
    abstract protected function ensureCheckout(string $action = ''): void;
    abstract protected function getProvider(): ?FeatureProvider;
    abstract protected function returnValue(bool $checkin, mixed $value): mixed;

    abstract public function checkout(?FeatureProvider $provider = null): static;
    abstract public function checkin(): static;
    abstract public function isCheckedOut(): bool;
    abstract public function isPrivate(): bool;
    abstract public function usesUniqueInstances(): bool;
    abstract public function getContract(): string;
    abstract public function get(string $identifier, ?FeatureProvider $provider = null, bool $checkin = true): FeatureDefinition|null;
    abstract public function has(string $identifier, ?Feature $excludingFeature = null, ?FeatureProvider $provider = null, bool $checkin = true): bool;
}