<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {
    
    protected function configure(): void {
        $this->required(true);
        $this->description('The meros_migrations table tracks table migrations run by the Meros framework.');

        $this->define(function (Blueprint $table) {
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
};