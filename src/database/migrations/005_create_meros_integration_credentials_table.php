<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('meros_integration_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->nullable()
                ->references('id')
                ->on('meros_integrations')
                ->cascadeOnDelete();

            $table->foreignId('connection_id')
                ->nullable()
                ->references('id')
                ->on('meros_integration_connections')
                ->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('type')->index();
            $table->json('credentials');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('meros_integration_credentials');
    }
};