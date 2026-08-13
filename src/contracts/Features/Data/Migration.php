<?php 

namespace MM\Meros\Contracts\Features\Data;

use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * The description of the migration.
     *
     * @var string
     */
    public string $description = '';

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