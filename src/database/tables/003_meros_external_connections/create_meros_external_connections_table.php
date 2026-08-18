<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {
    
    protected function configure(): void {
        $this->description('The `meros_external_connections` table stores external connection information for integrations.');

        $this->define(function (Blueprint $table) {
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
};