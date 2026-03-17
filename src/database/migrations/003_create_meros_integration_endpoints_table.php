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
        Schema::create('meros_integration_endpoints', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('integration_id')
                ->constrained('meros_integrations')
                ->cascadeOnDelete();
            
            $table->string('source');
            $table->string('label');
            $table->string('slug');
            
            $table->unique(
                ['integration_id', 'slug'],
                'integration_endpoint_unique'
            );

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
        Schema::dropIfExists('meros_integration_endpoints');
    }
};