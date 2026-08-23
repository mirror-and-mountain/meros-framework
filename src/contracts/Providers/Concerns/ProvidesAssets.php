<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Assets\AssetGroup;
use MM\Meros\Registers\Assets\AssetGroups;

use MM\Meros\Contracts\Features\Assets\Asset;
use MM\Meros\Registers\Assets\Assets;

trait ProvidesAssets {
    use Abstracts;

    /**
     * Resolves a specific asset group or the asset groups register based on the provided name.
     *
     * @param string $nameOrClass Optional. The name or class of the asset group to retrieve.
     *
     * @return AssetGroup|AssetGroups|null The requested asset group or the asset groups register.
     */
    final protected function assetGroups(string $nameOrClass = ''): AssetGroup|AssetGroups|null {
        return $this->resolveFeatureRequestFor(AssetGroup::class, $nameOrClass);
    }

    /**
     * Resolves a specific asset or the assets register based on the provided handle.
     * 
     * @param string $handleOrClass Optional. The handle or class of the asset to retrieve.
     *
     * @return Asset|Assets|null The assets register.
     */
    final protected function assets(string $handleOrClass = ''): Asset|Assets|null {
        return $this->resolveFeatureRequestFor(Asset::class, $handleOrClass);
    }
}