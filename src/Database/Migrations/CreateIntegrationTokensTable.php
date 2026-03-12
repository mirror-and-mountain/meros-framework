<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationTokensTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_integration_tokens_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 120;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            $table->text('access_token');
            $table->text('refresh_token')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->string('token_type')->default('Bearer');
            $table->json('scopes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('integration_tokens');
    }
};