<?php

namespace MM\Meros\Contracts;
use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * A human-readable label for the migration.
     *
     * @var string
     */
    public static string $label;

    /**
     * The priority of the migration. Lower numbers run first.
     *
     * @var int
     */
    public static int $priority;
}