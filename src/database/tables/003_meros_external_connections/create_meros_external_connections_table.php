<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        SchemaManager::create('meros_external_connections', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->string('integration_id')->index();
            $table->string('environment')->nullable()->index()->default('default');
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('api_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('id_token')->nullable();
            $table->text('scopes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('token_issued_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->string('status')->default('inactive')->index();
            $table->string('status_reason')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_external_connections', $installer);
    }
};