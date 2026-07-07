<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        SchemaManager::create('meros_integration_environments', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('integration_handle')->index();
            $table->string('environment')->index();
            $table->string('label');
            $table->boolean('is_default')->default(false)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'integration_handle', 'environment'],
                'meros_integration_environments_unique'
            );
        });
    }

    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_integration_environments', $installer);
    }
};
