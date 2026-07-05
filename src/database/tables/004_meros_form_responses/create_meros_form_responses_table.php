<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {

    public function up(string $installable): void {
        SchemaManager::create('meros_form_responses', $installable, function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')->references('id')->on('posts')->cascadeOnDelete();

            $table->json('response')->nullable();

            $table->string('status')->default('draft');

            $table->timestamps();
        });
    }


    public function down(string $installable): void {
        SchemaManager::dropIfExists('meros_form_responses', $installable);
    }
};