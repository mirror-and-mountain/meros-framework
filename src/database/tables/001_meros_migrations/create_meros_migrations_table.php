<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {

    public function up(string $installer): void {
        SchemaManager::create('meros_migrations', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('type');
            $table->string('label');
            $table->string('handle')->unique();
            $table->string('related_table')->unique();
            $table->string('path')->unique();
            $table->ulid('batch_id')->index();
            $table->timestamps();
        });
    }


    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_migrations', $installer);
    }
};