<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;
use MM\Meros\App\Features\Asset;

interface AssetsRegistrar {
    /**
     * Returns a collection of asset objects.
      *
      * @param  bool $readyOnly Whether to return only assets that are ready.
      *
      * @return Collection
      */
    public function getAssets(bool $readyOnly = false): Collection;

    /**
     * Returns a single asset by its handle.
     *
     * @param  string $handle The handle of the asset to retrieve.
     *
     * @return Asset|null
     */
    public function getAsset(string $handle): Asset|null;
}