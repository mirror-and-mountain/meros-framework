<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationEndpointsTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_integration_endpoints_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 110;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_endpoints', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();
            
            $table->string('source');
            $table->string('label');
            $table->string('slug');
            $table->unique(['integration_id', 'slug']);

            $table->text('description')->nullable();
            $table->string('uri'); 
            $table->string('method', 10)->index(); 
            $table->string('format')->default('json');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('integration_endpoints');
    }
};