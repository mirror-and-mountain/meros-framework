<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\App\Features\Abstracts\TableInstaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TableInstaller {

    public function up(): void {
        Schema::create('meros_integrations', function (Blueprint $table) {
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

    public function down(): void {
        Schema::dropIfExists('meros_integrations');
    }
};