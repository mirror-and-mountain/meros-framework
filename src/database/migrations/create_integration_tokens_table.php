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
    public int $priority = 120;

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