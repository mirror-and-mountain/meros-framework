<?php 

namespace MM\Meros\Support;

use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    abstract public function up(string $installer): void;

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    abstract public function down(string $installer): void;
}