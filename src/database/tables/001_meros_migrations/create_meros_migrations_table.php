<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\SchemaManager;
use MM\Meros\Contracts\Features\Data\Migration;

return new class extends Migration {
    public string $description = 'Creates the meros_migrations table to track migrations run by the Meros framework.';

    public function up(string $installer): void {
        SchemaManager::create('meros_migrations', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('type');
            $table->string('label');
            $table->string('handle')->index();
            $table->string('related_table')->index();
            $table->string('path')->index();
            $table->ulid('batch_id')->index();
            $table->timestamps();

            $table->index(['provider', 'batch_id']);
            $table->index(['provider', 'created_at']);
        });
    }


    public function down(string $installer): void {
        SchemaManager::dropIfExists('meros_migrations', $installer);
    }
};