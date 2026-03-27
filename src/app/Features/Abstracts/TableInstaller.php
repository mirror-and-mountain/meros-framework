<?php

namespace MM\Meros\App\Features\Abstracts;

use Illuminate\Database\Migrations\Migration;

abstract class TableInstaller extends Migration {
    /**
     * Run the installer.
     */
    abstract public function up(): void;

    /**
     * Reverse the installer.
     */
    abstract public function down(): void;
};