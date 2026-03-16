<?php

namespace MM\Meros\App\Services\Theme;

use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * The priority of the migration. Lower numbers run first. 
     * Must be unique among migrations from the same source.
     *
     * @var int
     */
    public int $priority;
}