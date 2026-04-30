<?php

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Block;
use MM\Meros\Services\Registers\Blocks as BlocksRegister;

use MM\Meros\Facades\Blocks;

trait HasBlocks {
    /**
     * Retrieves a block by its name or the blocks register.
     *
     * @param  string|null $name Optional. The name of the block to retrieve.
     * @param  Closure|null $callback Optional. A callback to modify the block before returning it.
     *
     * @return Block|BlocksRegister|null A new block instance or the requested block. Null if the requested block doesn't exist. If * is passed as the name, a collection of all blocks will be returned.
     */
    protected function blocks(string $name = '', ?Closure $callback = null): Block|BlocksRegister|null {
        if (empty($name)) {
            return Blocks::checkout($this); // return register instance
        }

        else {
            return Blocks::checkout($this)->get($name, $callback);
        }
    }
}