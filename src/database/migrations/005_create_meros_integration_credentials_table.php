<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\App\Features\Abstracts\TableInstaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TableInstaller {
    
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

    public function down(): void {
        Schema::dropIfExists('meros_integration_credentials');
    }
};