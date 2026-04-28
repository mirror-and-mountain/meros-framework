<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Asset;
use MM\Meros\Services\Registers\Assets as AssetsRegister;

use MM\Meros\Facades\Assets;

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
     * Retrieves an asset by its handle or the assets register.
     *
     * @param string $handle Optional. The handle of the asset to retrieve.
     *
     * @return Asset|AssetsRegister|null The requested asset or a collection of all assets. Null if the requested asset doesn't exist.
     */
    protected function assets(string $handle = '', ?Closure $callback = null): Asset|AssetsRegister|null {
        if (empty($handle)) {
            return Assets::checkout($this); // return register instance
        }

        else {
            return Assets::checkout($this)->get($handle, $callback);
        }
    }
}