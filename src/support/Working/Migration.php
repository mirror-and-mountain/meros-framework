<?php 

namespace MM\Meros\App\Support;

use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    abstract public function up(string $installable): void;

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    abstract public function down(string $installable): void;
}