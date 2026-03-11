<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationsTable extends Migration {
    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug = 'create_integrations_table';

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var string
     */
    public static int $priority = 105;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('label');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('api_base_uri')->nullable();
            $table->string('api_version')->nullable();
            $table->string('auth_type');
            $table->string('group')->nullable()->index();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('integrations');
    }
};