<?php 

namespace MM\Meros\App\Events\Migrations;

use MM\Meros\Contracts\Features\Data\Table;

class TableDropped {
    public function __construct(
        public string  $tableName, 
        public Table   $table, 
        public ?string $connection = null
    ) {}
}