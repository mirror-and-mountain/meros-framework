<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;
use MM\Meros\App\Features\Component;

interface ComponentsRegistrar {
    /**
     * Returns a collection of component objects.
      *
      * @param  bool $readyOnly Whether to return only components that are ready.
      *
      * @return Collection
      */
    public function getComponents(bool $readyOnly = false): Collection;

    /**
     * Returns a specific component object.
     *
     * @param  string $handle The component's handle.
     *
     * @return Component|null
     */
    public function getComponent(string $handle): Component|null;
}