<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        SchemaManager::create('meros_integration_connections', $installer, function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('meros_integration_accounts')->cascadeOnDelete();
            $table->string('label');
            $table->text('api_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('id_token')->nullable();
            $table->text('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_integration_connections', $installer);
    }
};