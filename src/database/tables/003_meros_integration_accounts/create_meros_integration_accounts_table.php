<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        SchemaManager::create('meros_integration_accounts', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('integration_handle')->index();
            $table->string('label');
            $table->string('category')->index();
            $table->string('auth_type')->index();
            $table->string('environment')->default('production')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'integration_handle', 'environment', 'label'], 'meros_integration_accounts_unique');
        });
    }

    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_integration_accounts', $installer);
    }
};