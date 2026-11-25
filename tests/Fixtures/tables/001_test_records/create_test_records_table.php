<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {
    protected function configure(): void
    {
        $this->define(function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
    }
};
