<?php 

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Collection;
use MM\Meros\App\Support\Asset;

trait HasAssets {
    /**
     * Instantiates a new Asset class and returns it for configuration.
     *
     * @return Asset The newly created Asset instance.
     */
    protected function enqueue(): Asset {
        $asset = app(Asset::class, ['source' => $this]);
        return $this->registry()->add('assets', $asset);
    }

    /**
     * Retrieves an asset by its handle or returns a collection of all assets.
     *
     * @param string|null $handle The handle of the asset to retrieve. If null, returns all assets.
     *
     * @return Asset|Collection|null The requested asset or a collection of all assets. Null if the requested asset doesn't exist.
     */
    protected function assets(?string $handle = null): Asset|Collection|null {
        if ($handle) {
            return $this->registry()->get('assets')->firstWhere('handle', $handle);
        }

        return $this->registry()->get('assets');
    }
}