<?php

namespace MM\Meros\Contracts\Features\Components\Concerns;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\FieldRow;

trait MakesFieldRows {
    /**
     * FieldRow instances or definitions that belong to the item.
     *
     * @var array<FieldRow|array>
     */
    protected array $rows = [];

    abstract protected function getProvider(): FeatureProvider;

    /**
     * Instantiates row definitions into FieldRow instances.
     *
     * @return void
     */
    private function instantiateRows(): void {
        $this->rows = array_map(function ($row) {
            if ($row instanceof FieldRow) {
                return $row;
            }

            return $this->makeNewRow($row['fields'] ?? [], false);
        }, $this->rows);
    }

    /**
     * Adds a row to the item.
     *
     * @param FieldRow|Closure|array $row      The row to add, which can be a FieldRow instance, an array of properties, or a closure that configures the row.
     * @param Closure|null           $callback An optional callback to configure the row if it is provided as a closure.
     *
     * @return static Returns the current instance for method chaining.
     */
    final public function row(FieldRow|Closure|array $row, ?Closure $callback = null): static {
        if ($row instanceof FieldRow) {
            $rowInstance = $row;
            $this->rows[] = $rowInstance;
        }

        else if (is_array($row)) {
            $rowInstance  = $this->makeNewRow($row['fields'] ?? []);
        }

        else {
            $callback     = $row;
            $rowInstance  = $this->makeNewRow();
        }

        if ($callback) {
            $callback($rowInstance);
        }

        return $this;
    }

    /**
     * Creates and returns a new FieldRow instance, optionally with specified fields.
     * 
     * @param array $fields An optional array of fields to initialise the new FieldRow instance with.
     * @param bool  $add    Whether to add the new FieldRow instance to the rows array. Defaults to true.
     *
     * @return FieldRow A new FieldRow instance.
     */
    private function makeNewRow(array $fields = [], bool $add = true): FieldRow {
        $row = FieldRow::make(
            $this->getProvider(), 
            $fields, 
            $this->resolveForm(), 
            $this instanceof FieldGroup ? $this : null
        );
        
        if ($add) {
            $this->rows[] = $row;
        }

        return $row;
    }

    /**
     * Retrieves the last FieldRow instance in the rows array, or creates a new one if none exists and $makeNew is true.
     * 
     * @param bool $makeNew Whether to create a new FieldRow instance if none exists. Defaults to false.
     *
     * @return FieldRow|null The last FieldRow instance or null if there are no rows.
     */
    private function getLastRow(bool $makeNew = false): ?FieldRow {
        $lastRow = end($this->rows);

        if ($lastRow instanceof FieldRow) {
            return $lastRow;
        }

        if ($makeNew) {
            return $this->makeNewRow();
        }

        return null;
    }

    /**
     * Resolves the Form instance associated with the item, if any.
     *
     * @return Form|null The associated Form instance or null if not available.
     */
    private function resolveForm(): ?Form {
        if (property_exists($this, 'form') && 
            isset($this->form) && 
            $this->form instanceof Form
        ) {
            return $this->form;
        }

        if ($this instanceof Form) {
            return $this;
        }

        return null;
    }

    /**
     * Returns the component's rows.
     * 
     * @param boolean $collect Whether to return the rows as a Collection. If false, returns as an array.
     *
     * @return array<FieldRow>|Collection<FieldRow>
     */
    final public function getRows(bool $collect = false): array|Collection {
        return $collect ? collect($this->rows) : $this->rows;
    }
}