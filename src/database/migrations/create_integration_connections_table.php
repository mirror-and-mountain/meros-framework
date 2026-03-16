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
    public int $priority = 115;

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