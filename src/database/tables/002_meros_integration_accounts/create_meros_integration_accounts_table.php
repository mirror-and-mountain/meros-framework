<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {

    public function up(string $installable): void {
        SchemaManager::create('meros_integration_accounts', $installable, function (Blueprint $table) {
            $table->id();
            $table->string('handle'); // 'salesforce', 'stripe'
            $table->string('label');

            $table->json('credentials_json'); // encrypted
            $table->timestamps();
        });
    }


    public function down(string $installable): void {
        SchemaManager::dropIfExists('meros_integration_accounts', $installable);
    }
};