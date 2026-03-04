<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerosMigrationsTable extends Migration {
    public static string $label = 'Create Meros Migrations Table';
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