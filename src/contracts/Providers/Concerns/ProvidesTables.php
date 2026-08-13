<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Data\Table;
use MM\Meros\Registers\Data\Tables;

trait ProvidesTables {
    use Abstracts;

    /**
     * Resolves a specific table or the tables register based on the provided handle.
     *
     * @param string $handle Optional. The handle of the table to retrieve.
     *
     * @return Table|Tables|null The requested table or the tables register.
     */
    final protected function tables(string $handle = ''): Table|Tables|null {
        return $this->resolveRequestFor(Table::class, $handle);
    }
}