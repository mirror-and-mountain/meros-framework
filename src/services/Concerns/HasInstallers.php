<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use MM\Meros\Services\Contracts\Table;
use MM\Meros\Services\Registers\Tables as TableRegister;

use MM\Meros\Facades\Tables;

trait HasInstallers {
    /**
     * Retrieves a table by its handle or the tables register.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return Table|TableRegister|null
     */
    protected function tables(string $handle = '', ?Closure $callback = null): Table|TableRegister|null {
        if (empty($handle)) {
            return Tables::checkout($this); // return register instance
        }

        else {
            return Tables::checkout($this)->get($handle, $callback);
        }
    }
}