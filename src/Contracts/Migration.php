<?php

namespace MM\Meros\Contracts;
use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {

    /**
     * A unique slug for the migration, used for tracking which migrations have been run.
     *
     * @var string
     */
    public static string $slug;

    /**
     * A human-readable label for the migration.
     *
     * @var string
     */
    public static string $label = '';

    /**
     * The priority of the migration. Lower numbers run first. 
     * Must be unique among migrations from the same source.
     *
     * @var int
     */
    public static int $priority;
}