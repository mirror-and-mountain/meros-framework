<?php 

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use MM\Meros\Support\SchemaManager;

abstract class TableUpdater extends Migration {
    /**
     * The update definition for the migration.
     *
     * @var object
     */
    private object $updateDefinition;

    /**
     * The rollback definition for the migration.
     *
     * @var object
     */
    private object $rollbackDefinition;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Sets the update definition for the migration.
     *
     * @param string|Closure $callbackOrLabel
     * @param Closure|null   $callback
     * 
     * @return void
     */
    final protected function update(
        string|Closure $callbackOrLabel, 
        Closure|null   $callback = null
    ): void {
        $params   = $this->resolveLabelAndCallback($callbackOrLabel, $callback);
        $label    = $params[0];
        $callback = $params[1];

        $this->updateDefinition = (object) [
            'label'      => $label,
            'callback'   => $callback,
        ];
    }

    /**
     * Sets the rollback definition for the migration.
     *
     * @param string|Closure $callbackOrLabel
     * @param Closure|null   $callback
     * 
     * @return void
     */
    final protected function rollback(
        string|Closure $callbackOrLabel, 
        Closure|null   $callback = null
    ): void {
        $params   = $this->resolveLabelAndCallback($callbackOrLabel, $callback);
        $label    = $params[0];
        $callback = $params[1];

        $label = $label . ' (Rollback)';

        $this->rollbackDefinition = (object) [
            'label'      => $label,
            'callback'   => $callback
        ];
    }

    // =========================================================================
    // Operations
    // =========================================================================

    /**
     * Run the migration.
     * 
     * @param Table $table
     *
     * @return void
     */
    final public function up(Table $table): void {
        if (!isset($this->updateDefinition, $this->rollbackDefinition)) {
            throw new \RuntimeException('No update or rollback definition provided for the migration. These should be set using the update() and rollback() methods in the init() method of the migration class.');
        }

        SchemaManager::update(
            $this->updateDefinition->label,
            $this->getHandle(),
            $table,
            $this->updateDefinition->callback,
            $table->getConnection()
        );
    }

    /**
     * Reverse the migration.
     * 
     * @param Table|string $table
     *
     * @return void
     */
    final public function down(Table|string $table): void {
        if (!isset($this->updateDefinition, $this->rollbackDefinition)) {
            throw new \RuntimeException('No update or rollback definition provided for the migration. These should be set using the update() and rollback() methods in the init() method of the migration class.');
        }

        SchemaManager::rollback(
            $this->rollbackDefinition->label,
            $this->getHandle() . '_rollback',
            $table,
            $this->rollbackDefinition->callback,
            $table->getConnection()
        );
    }
}