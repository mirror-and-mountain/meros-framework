<?php 

namespace MM\Meros\Registers\Data;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Data\Table;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Data\Tables as TablesFacade;

class Tables extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->definition(Table::class);
        $this->facade(TablesFacade::class);
    }

    /**
     * Instantiates a new Table instance from a given path.
     *
     * @param string $path
     *
     * @return Table
     */
    final public function makeFromPath(string $path): Table {
        $this->ensureCheckout('makeFromPath');
        return $this->make(['path' => $path]);
    }
}