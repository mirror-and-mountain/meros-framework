<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\App\Support\Admin\Migration;
use MM\Meros\App\Support\Admin\SchemaManager;

return new class extends Migration {

    public function up(string $installable): void {
        SchemaManager::create('meros_migrations', $installable, function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('type');
            $table->string('subtype');
            $table->string('label');
            $table->string('handle')->unique();
            $table->string('related_table')->unique();
            $table->string('path')->unique();
            $table->ulid('batch_id')->index();
            $table->timestamps();
        });
    }


    public function down(string $installable): void {
        SchemaManager::dropIfExists('meros_migrations', $installable);
    }
};