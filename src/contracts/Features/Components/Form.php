<?php

namespace MM\Meros\Contracts\Features\Components;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Contracts\Features\Components\Concerns\IsFormComponent;
use MM\Meros\Contracts\Features\Components\Concerns\MakesFieldRows;

class Form extends Feature implements FormComponent, Makeable {
    /**
     * The form's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The form's title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The form's description.
     *
     * @var string
     */
    protected string $description = '';

    private string $view = 'meros::forms.form';

    use IsFormComponent,
        IsMakeable,
        MakesFieldRows,
        InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->setSerializableProperties(([
            'id',
            'title',
            'description',
            'rows'
        ]));

        $this->set('id', $this->passedProps['id'] ?? 'mforms-' . Str::substr(Str::uuid(), 0, 8));
        $this->set('title', $this->passedProps['title'] ?? '');
        $this->set('description', $this->passedProps['description'] ?? '');
        $this->set('rows', $this->passedProps['rows'] ?? []);
    }

    final protected function whenConfigured(): void {
        if (!empty($this->rows)) {
            $this->instantiateRows();
        } else {
            // Create an initial row if none are provided
            $this->makeNewRow();
        }
    }

    // =========================================================================
    // Row, Field & Group Management
    // =========================================================================

    /**
     * Adds a field to the FieldGroup instance. If the last row has reached its field capacity, a new FieldRow instance is created for the field.
     *
     * @param string        $type The type of the field to add.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     * @param bool          $autoRow Whether to automatically add the field to an existing row if it has capacity (true) or always create a new row for the field (false). Defaults to true.
     *
     * @return static Returns the current instance for method chaining.
     * @throws \RuntimeException If the field could not be created.
     */
    final public function field(string $type, Closure|array $callbackOrProps = [], bool $autoRow = true): static {
        $field = $this->makeItemFrom($type, Field::class, $callbackOrProps);

        if (!($field instanceof Field)) {
            throw new \RuntimeException("Failed to create a Field instance of type '{$type}'.");
        }

        $lastRow = $this->getLastRow(true);

        if ($autoRow) {
            $fieldWidth     = $field->getRowPositions();
            $rowHasCapacity = $lastRow->hasCapacityFor($fieldWidth);

            if ($rowHasCapacity) {
                $lastRow->field($type, $callbackOrProps);
                return $this;
            }
        }

        if ($lastRow->isEmpty()) {
            $lastRow->field($type, $callbackOrProps);
            return $this;
        }

        return $this->row(function ($row) use ($type, $callbackOrProps) {
            $row->field($type, $callbackOrProps);
        });
    }

    /**
     * Adds a FieldGroup to the form. If the last row has reached its field capacity, a new FieldRow instance is created for the group.
     *
     * @param FieldGroup|Closure|string|array $section         The FieldGroup to add, which can be a FieldGroup instance, an array of properties, an existing group id, or a closure that configures the group.
     * @param Closure|array                   $callbackOrProps An optional callback to configure the group or an array of properties to pass to the group's constructor.
     *
     * @return static Returns the current instance for method chaining.
     */
    final public function section(FieldGroup|Closure|string|array $section, Closure|array $callbackOrProps = []): static {
        $row = $this->getLastRow(true);
        $rowHasCapacity = $row->hasCapacityFor(3);

        if (!$rowHasCapacity) {
            $row = $this->makeNewRow();
        }
        
        $row->group($section, $callbackOrProps);
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $id): static {
        return $this->id($id);
    }

    /**
     * Sets the form's id.
     *
     * @param string $id
     *
     * @return static
     */
    final public function id(string $id): static {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the form's title.
     *
     * @param string $title
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the form's description.
     *
     * @param string $description
     *
     * @return static
     */
    final public function description(string $description): static {
        $this->description = $description;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    final public function getIdentifier(): string {
        return $this->id;
    }

    /**
     * Returns the form's id.
     *
     * @return string
     */
    final public function getId(): string {
        return $this->id;
    }

    /**
     * Returns the form's title.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    /**
     * Returns the form's description.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the form's rows.
     *
     * @return array
     */
    final public function getRows(): array {
        return $this->rows;
    }

    /**
     * Returns the index of the given FieldRow instance within the form's rows.
     *
     * @param FieldRow $row The FieldRow instance to find the index of.
     * @return int|null The index of the FieldRow instance, or null if not found.
     */
    final public function getRowIndex(FieldRow $row): ?int {
        $index = array_search($row, $this->rows, true);
        return $index !== false ? $index : null;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function render(array $properties = [], bool $mergeProperties = false): void {
        $view = $this->view;

        if ($mergeProperties) {
            $properties = array_merge(
                $this->filterSerializedProperties($this->toArray()['properties'] ?? []),
                $properties
            );
        } 
        
        else {
            $properties = empty($properties) 
                ? $this->filterSerializedProperties($this->toArray()['properties'] ?? [])
                : $properties;
        }

        echo view($view, $properties);
    }

    public function html(array $properties = [], bool $mergeProperties = false): string {
        ob_start();
        $this->render($properties, $mergeProperties);

        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }
}