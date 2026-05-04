<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\App\Package;
use Illuminate\Support\Collection;

use MM\Meros\App\Providers\PackageServiceProvider;

final class Packages {
    /**
     * The registered packages.
     *
     * @var array
     */
    private array $packages;

    /**
     * The Service Provider this register is checked out to.
     *
     * @var PackageServiceProvider|null
     */
    private ?PackageServiceProvider $provider = null;

    public function __construct() {
        $this->packages = [];
    }

    /**
     * Checkout to the package service provider.
     *
     * @param  PackageServiceProvider $provider
     * @return self
     */
    public function checkout(PackageServiceProvider $provider): self {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Register a package.
     *
     * @param  Package $package
     * @return void
     */
    public function register(Package $package): void {
        $this->ensureCheckout();

        if (!in_array($package, $this->packages)) {
            $this->packages[] = $package;
        }

        $this->checkin();
    }

    /**
     * Get all registered packages.
     *
     * @return Collection
     */
    public function all(): Collection {
        return collect($this->packages);
    }

    /**
     * Get a package by its handle.
     *
     * @param  string $handle
     * @return Package|null
     */
    public function get(string $handle): ?Package {
        return $this->all()->firstWhere('handle', $handle);
    }

    /**
     * Ensure that the register is checked out to the package service provider.
     *
     * @return void
     */
    private function ensureCheckout(): void {
        if ($this->provider === null) {
            throw new \LogicException('The package service provider is currently checked out.');
        }
    }

    /**
     * Check in from the package service provider.
     *
     * @return void
     */
    private function checkin(): void {
        $this->provider = null;
    }
}