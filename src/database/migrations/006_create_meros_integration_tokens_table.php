<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\App\Features\Abstracts\TableInstaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TableInstaller {

    public function up(): void {
        Schema::create('meros_integration_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('connection_id')
                ->references('id')
                ->on('meros_integration_connections')
                ->cascadeOnDelete();

            $table->text('access_token');
            $table->text('refresh_token')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->string('token_type')->default('Bearer');
            $table->json('scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('meros_integration_tokens');
    }
};