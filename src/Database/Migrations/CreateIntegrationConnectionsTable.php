<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationConnectionsTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_integration_connections_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 115;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();
            
            $table->string('label')->nullable();
            $table->string('account_id');
            $table->unique(['integration_id', 'account_id']);
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
        Schema::dropIfExists('integration_connections');
    }
};