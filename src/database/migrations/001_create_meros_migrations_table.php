<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meros_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('type');
            $table->string('label');
            $table->string('slug')->unique();
            $table->string('path_reference')->unique();
            $table->ulid('batch_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('meros_migrations');
    }
};