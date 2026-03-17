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
        Schema::create('meros_integration_connections', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('integration_id')
                ->references('id')
                ->on('meros_integrations')
                ->cascadeOnDelete();
            
            $table->string('label')->nullable();
            $table->string('account_id');
            
            $table->unique(
                ['integration_id', 'account_id'],
                'integration_account_unique'
            );

            $table->string('instance_url')->nullable();

            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('meros_integration_connections');
    }
};