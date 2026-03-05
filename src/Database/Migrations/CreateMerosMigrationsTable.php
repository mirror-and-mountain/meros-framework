<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerosMigrationsTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_meros_migrations_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 100;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('db_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('label');
            $table->string('slug')->unique();
            $table->integer('priority');
            $table->string('path_reference')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('db_migrations');
    }
};