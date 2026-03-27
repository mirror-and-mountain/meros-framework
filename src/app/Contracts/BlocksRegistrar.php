<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;
use MM\Meros\App\Features\Block;

interface BlocksRegistrar {
    /**
     * Returns a collection of block objects.
     *
     * @param  bool $readyOnly Whether to return only blocks that are ready.
     *
     * @return Collection
     */
    public function getBlocks(bool $readyOnly = false): Collection;

    /**
     * Returns a specific block object.
     *
     * @param  string $handle The block's handle.
     *
     * @return Block|null
     */
    public function getBlock(string $handle): Block|null;
}