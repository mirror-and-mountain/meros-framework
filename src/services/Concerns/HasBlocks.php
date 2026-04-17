<?php

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Collection;
use MM\Meros\App\Support\Block;

trait HasBlocks {
    /**
     * Instantiates a new block instance or retrieves an existing one from the registry 
     * if a name is provided and a block with that name exists.
     *
     * @param  string|null $name The name of the block to retrieve. If null, a new block instance will be created.
     *
     * @return Block|Collection|null A new block instance or the requested block. Null if the requested block doesn't exist. If * is passed as the name, a collection of all blocks will be returned.
     */
    protected function blocks(?string $name = null): Block|Collection|null {
        if ($name && $name !== '*') {
            $block = $this->registry()->get('blocks')->firstWhere('name', $name);
            if ($block) {
                return $block;
            }
        }

        if ($name === '*') {
            return $this->registry()->get('blocks');
        }

        return app(Block::class, ['source' => $this]);
    }
}