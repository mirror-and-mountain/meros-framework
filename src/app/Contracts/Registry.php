<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;

interface Registry {
    /**
     * Generic method to add an item to one of this object's collections.
     * Specific methods will be added for individual collections e.g. addPackage(), addSettingPage() etc.
     *
     * @param  string $type The collection to add to e.g. 'settings', 'packages' etc.
     * @param  FeatureBuilder $item The item to add.
     * 
     * @return void
     */
    public function add(string $type, FeatureBuilder $item): void;

    /**
     * Generic method to get one of this object's collections.
     * Specific methods are also available below e.g. getSettings();
     *
     * @param  string $type The collection to get e.g. 'settings', 'packages' etc.
     * 
     * @return Collection
     */
    public function get(string $type): Collection;
}