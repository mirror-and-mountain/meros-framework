<?php

namespace MM\Meros\Registers\Content;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Content\Taxonomy;
use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;
use MM\Meros\Facades\Content\Taxonomies as TaxonomiesFacade;

class Taxonomies extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    /**
     * Configures the taxonomy register's contract, facade and identifier format.
     *
     * @return void
     */
    protected function configure(): void {
        $this->unique(true);
        $this->contract(Taxonomy::class);
        $this->facade(TaxonomiesFacade::class);
        $this->identifierFormat('slug');
    }
}
