<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {

    public function up(string $installable): void {
        SchemaManager::create('meros_integration_connections', $installable, function (Blueprint $table) {
            $table->id();

            $table->string('integration_key');
            $table->foreignId('integration_account_id')->references('id')->on('meros_integration_accounts')->cascadeOnDelete();

            $table->string('external_id')->nullable(); // org id, account id
            $table->string('auth_type'); // 'oauth', 'api_key', 'jwt'

            $table->json('credentials_json'); // encrypted
            $table->json('meta_json')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();
        });
    }


    public function down(string $installable): void {
        SchemaManager::dropIfExists('meros_integration_connections', $installable);
    }
};