<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {

    protected function configure(): void {
        $this->description('The meros_form_responses table stores responses to forms.');

        $this->define(function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->json('response')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }
};