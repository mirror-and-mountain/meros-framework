<?php 

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use MM\Meros\Support\SchemaManager;

abstract class TableCreator extends Migration {
    /**
     * Whether or not the table is required for the provider to function. Defaults to false.
     *
     * @var boolean
     */
    private bool $required = false;
    
    /**
     * The table definition for the migration.
     *
     * @var object
     */
    private object $definition;

    /**
     * The list of table names that this migration depends on.
     *
     * @var array
     */
    private array $dependsOn = [];

    /**
     * Whether to automatically install this table when its dependencies are installed. Defaults to true.
     *
     * @var boolean
     */
    private bool $installWithDependencies = true;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Sets whether this table is required for the provider to function.
     *
     * @param boolean $isRequired
     *
     * @return void
     */
    final protected function required(bool $isRequired = true): void {
        $this->required = $isRequired;
    }

    /**
     * Add a table dependency (or multiple table dependencies) for this migration.
     *
     * @param string $tableName
     * @param bool   $autoInstall
     *
     * @return void
     */
    final protected function dependsOn(string $tableName, bool $autoInstall = true): void {
        if (!in_array($tableName, $this->dependsOn)) {
            $this->dependsOn[] = $tableName;
        }

        $this->installWithDependencies = $autoInstall;
    }

    /**
     * Sets the schema definition for the migration.
     *
     * @param string|Closure $labelOrCallback
     * @param Closure|null   $callback
     * @param string|null    $connection
     * 
     * @return void
     */
    final protected function define(
        string|Closure $labelOrCallback, 
        Closure|null   $callback = null, 
        string|null    $connection = null
    ): void {
        if ($labelOrCallback instanceof Closure) {
            $callback = $labelOrCallback;
            $label = $this->generateLabel();
        } else {
            $label = $labelOrCallback;
            $this->label($label);
        }

        if ($callback === null) {
            throw new \InvalidArgumentException('Callback must be provided when defining a table.');
        }

        $this->definition = (object) [
            'label'        => $label,
            'callback'     => $callback
        ];

        $this->connection = $connection;
    }

    // =========================================================================
    // Operations
    // =========================================================================

    /**
     * Run the migrations.
     * 
     * @param Table $table
     *
     * @return void
     */
   final public function up(Table $table): void {
        if (!isset($this->definition)) {
            throw new \RuntimeException('Table definition not set. Please use the define() method in the init() method.');
        }

        SchemaManager::create(
            $this->definition->label,
            $this->getHandle(),
            $table,
            $this->definition->callback,
            $this->dependsOn,
            $this->getConnection()
        );
   }

    /**
     * Reverse the migrations.
     * 
     * @param Table|string $table
     *
     * @return void
     */
    final public function down(Table|string $table): void {
        if (!isset($this->definition)) {
            throw new \RuntimeException('Table definition not set. Please use the define() method in the init() method.');
        }

        SchemaManager::dropIfExists(
            $table,
            $this->getConnection()
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns whether this table is required for the provider to function.
     *
     * @return boolean
     */
    final public function isRequired(): bool {
        return $this->required;
    }

    /**
     * Returns the list of table names that this migration depends on.
     *
     * @return array
     */
    final public function getDependencies(): array {
        return $this->dependsOn;
    }

    /**
     * Returns whether this table should be automatically installed when its dependencies are installed.
     *
     * @return bool
     */
    final public function installWithDependencies(): bool {
        return $this->installWithDependencies;
    }
}