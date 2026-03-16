<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Services\Theme\Migration;

return new class extends Migration {
    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var int
     */
    public int $priority = 125;

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')->nullable()->references('id')->on('integrations')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->references('id')->on('integration_connections')->cascadeOnDelete();

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