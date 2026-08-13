<?php

namespace MM\Meros\Registers;

use Illuminate\Support\Collection;

use MM\Meros\App\Package;

final class Packages {
    private Collection $packages;

    public function __construct() {
        $this->packages = collect([]);
    }

    /**
     * Adds a package to the register if it isn't already present.
     *
     * @param  Package $package
     * @return void
     */
    public function register(Package $package): void {
        if (!$this->packages->contains($package)) {
            $this->packages->push($package);
        }
    }

    /**
     * Gets a package by its handle.
     *
     * @param string $handle
     *
     * @return Package|null
     */
    public function get(string $handle): ?Package {
        return $this->packages->first(fn(Package $package) => $package->getHandle() === $handle);
    }

    /**
     * Gets all registered packages.
     *
     * @return Collection
     */
    public function all(): Collection {
        return $this->packages;
    }
}