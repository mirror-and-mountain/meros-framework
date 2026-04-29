<?php 

namespace MM\Meros\App\Events\Migrations;

class TableUpdated {
    public function __construct(
        public string  $table, 
        public string  $installerHandle, 
        public ?string $connection = null
    ) {}
}