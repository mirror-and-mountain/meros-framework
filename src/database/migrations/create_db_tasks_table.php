<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Services\Theme\Migration;

return new class extends Migration {
    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var int
     */
    public int $priority = 100;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('db_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('type');
            $table->string('label');
            $table->string('slug')->unique();
            $table->integer('priority');
            $table->string('path_reference')->unique();
            $table->ulid('batch_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('db_tasks');
    }
};