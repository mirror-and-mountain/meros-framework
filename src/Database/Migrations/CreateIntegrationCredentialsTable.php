<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationCredentialsTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_integration_credentials_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 125;

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->nullable()
                ->constrained('integrations')
                ->cascadeOnDelete()
                ->index();

            $table->foreignId('connection_id')
                ->nullable()
                ->constrained('integration_connections')
                ->cascadeOnDelete()
                ->index();

            $table->string('name')->nullable();
            $table->string('type')->index();
            $table->json('credentials');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('integration_credentials');
    }
};