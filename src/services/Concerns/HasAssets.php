<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Asset;
use MM\Meros\Services\Registers\Assets as AssetsRegister;

use MM\Meros\Services\Contracts\AssetGroup;
use MM\Meros\Services\Registers\AssetGroups as AssetGroupsRegister;

use MM\Meros\Facades\Assets;
use MM\Meros\Facades\AssetGroups;

trait HasAssets {
    /**
     * Retrieves an asset by its handle or the assets register.
     *
     * @param string $handle Optional. The handle of the asset to retrieve.
     *
     * @return Asset|AssetsRegister|null The requested asset or a collection of all assets. Null if the requested asset doesn't exist.
     */
    protected function assets(string $handle = '', ?Closure $callback = null): Asset|AssetsRegister|null {
        if (empty($handle)) {
            return Assets::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return Assets::get($handle, $this->resolveAuthority(), $callback);
        }
    }

    /**
     * Retrieves an asset group by its handle or the asset groups register.
     *
     * @param string $handle Optional. The handle of the asset group to retrieve.
     *
     * @return AssetGroup|AssetGroupsRegister|null The requested asset group or a collection of all asset groups. Null if the requested asset group doesn't exist.
     */
    protected function assetGroups(string $handle = '', ?Closure $callback = null): AssetGroup|AssetGroupsRegister|null {
        if (empty($handle)) {
            return AssetGroups::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return AssetGroups::get($handle, $this->resolveAuthority(), $callback);
        }
    } 
}