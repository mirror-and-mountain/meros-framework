<?php

namespace MM\Meros\Database\Migrations;

use MM\Meros\App\Features\Abstracts\TableInstaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TableInstaller {

    public function up(): void {
        Schema::create('meros_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('type');
            $table->string('subtype');
            $table->string('label');
            $table->string('handle')->unique();
            $table->string('path_reference')->unique();
            $table->ulid('batch_id')->index();
            $table->timestamps();
        });
    }


    public function down(): void {
        Schema::dropIfExists('meros_migrations');
    }
};