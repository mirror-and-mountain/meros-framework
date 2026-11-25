<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableUpdater;

return new class extends TableUpdater {
    protected function configure(): void
    {
        $this->update(fn (Blueprint $table) => $table->string('status')->default('new'));
        $this->rollback(fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
