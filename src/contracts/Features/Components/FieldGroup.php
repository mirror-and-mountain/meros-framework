<?php

namespace MM\Meros\Contracts\Features\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Contracts\Features\Components\Concerns\IsFormComponent;
use MM\Meros\Contracts\Features\Components\Concerns\MakesFieldRows;

class FieldGroup extends Feature implements FormComponent, Makeable {
    /**
     * The field group's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The field group's title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The field group's description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The field group's parent Form instance, if any.
     *
     * @var Form|null
     */
    protected ?Form $form = null;

    private string $view = 'meros::forms.field-group';

    use IsFormComponent,
        IsMakeable,
        InstantiatesItems,
        MakesFieldRows;

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

        $this->set('id', $this->passedProps['id'] ?? 'mforms-section-' . Str::substr(Str::uuid(), 0, 8));
        $this->set('title', $this->passedProps['title'] ?? '');
        $this->set('description', $this->passedProps['description'] ?? '');
        $this->set('rows', $this->passedProps['rows'] ?? []);
        
        if ($this->passedProps['form'] ?? null instanceof Form) {
            $this->form($this->passedProps['form']);
        }
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
    // Row & Field Management
    // =========================================================================

    /**
     * Adds a field to the FieldGroup instance. If the last row has reached its field capacity, a new FieldRow instance is created for the field.
     *
     * @param string        $type The type of the field to add.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     * @param bool          $autoRow Whether to automatically add the field to an existing row if it has capacity (true) or always create a new row for the field (false). Defaults to true.
     * @param bool          $returnField Whether to return the created Field instance (true) or the FieldGroup instance (false). Defaults to false.
     *
     * @return static|Field Returns the current instance for method chaining, or the created Field instance if $returnField is true.
     * @throws \RuntimeException If the field could not be created.
     */
    final public function field(string $type, Closure|array $callbackOrProps = [], bool $autoRow = true, bool $returnField = false): static|Field {
        $field = $this->makeItemFrom($type, Field::class, $callbackOrProps);

        if (!($field instanceof Field)) {
            throw new \RuntimeException("Failed to create a Field instance of type '{$type}'.");
        }

        $resolveAddedField = function (FieldRow $row) use ($type, $callbackOrProps): Field {
            $row->field($type, $callbackOrProps);
            $addedField = $row->getLastField();

            if (!($addedField instanceof Field)) {
                throw new \RuntimeException("Failed to add a Field instance of type '{$type}' to the row.");
            }

            return $addedField;
        };

        $lastRow = $this->getLastRow(true);

        if ($autoRow) {
            $fieldWidth     = $field->getRowPositions();
            $rowHasCapacity = $lastRow->hasCapacityFor($fieldWidth);

            if ($rowHasCapacity) {
                $field = $resolveAddedField($lastRow);
                return $returnField ? $field : $this;
            }
        }

        if ($lastRow->isEmpty()) {
            $field = $resolveAddedField($lastRow);
            return $returnField ? $field : $this;
        }
        
        $field = null;

        $this->row(function ($row) use ($type, $callbackOrProps, &$field) {
            $row->field($type, $callbackOrProps);
            $field = $row->getLastField();
        });

        if (!($field instanceof Field)) {
            throw new \RuntimeException("Failed to add a Field instance of type '{$type}' to a new row.");
        }

        return $returnField ? $field : $this;
    }

    /**
     * Sets the parent Form instance for the FieldGroup.
     *
     * @param Form $form The parent Form instance.
     * 
     * @return static Returns the current instance for method chaining.
     */
    final public function form(Form $form): static {
        $this->form = $form;
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $id): static {
        return $this->id($id);
    }

    /**
     * Sets the identifier for the FieldGroup instance.
     *
     * @param string $id The identifier to set for the FieldGroup.
     * @return static Returns the current instance for method chaining.
     */
    final public function id(string $id): static {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the title for the FieldGroup instance.
     *
     * @param string $title The title to set for the FieldGroup.
     * @return static Returns the current instance for method chaining.
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the description for the FieldGroup instance.
     *
     * @param string $description The description to set for the FieldGroup.
     * @return static Returns the current instance for method chaining.
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
     * Returns the field group's id.
     *
     * @return string
     */
    final public function getId(): string {
        return $this->id;
    }

    /**
     * Returns the field group's title.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    /**
     * Returns the field group's description.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the index of the specified FieldRow instance within the FieldGroup's rows.
     *
     * @param FieldRow $row The FieldRow instance to find the index of.
     * @return int|null The index of the FieldRow if found, or null if not found.
     */
    final public function getRowIndex(FieldRow $row): ?int {
        $index = array_search($row, $this->rows, true);
        return $index !== false ? $index : null;
    }

    /**
     * Returns the rows of the FieldGroup instance as an array or a Collection, based on the $collect parameter.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    final public function getRows(bool $collect = false): array|Collection {
        return $collect ? collect($this->rows) : $this->rows;
    }

    /**
     * Returns all Field instances within the FieldGroup, optionally as a Collection.
     *
     * @param boolean $collect Whether to return the fields as a Collection (true) or an array (false). Defaults to false.
     *
     * @return array|Collection An array or Collection of Field instances.
     */
    final public function getFields(bool $collect = false): array|Collection {
        $fields = [];

        foreach ($this->rows as $row) {
            if ($row instanceof FieldRow) {
                $fields = array_merge($fields, $row->getFields(true)->all());
            }
        }

        return $collect ? collect($fields) : $fields;
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

    /**
     * Renders the field group as a meta box, populating the default values of its fields with those provided in the $values array.
     * 
     * For internal use only.
     *
     * @param string $containerName
     * @param array $values
     *
     * @return string
     */
    final public function __renderAsMetaBox(string $containerName, array $values): string {
        $properties = $this->filterSerializedProperties($this->toArray()['properties'] ?? []);

        if (!array_key_exists('rows', $properties)) {
            return $this->html();
        }

        $rows = $properties['rows'];
        foreach ($rows as $rowIndex => $row) {
            if (!array_key_exists('properties', $row)) {
                continue;
            }

            if (!array_key_exists('fields', $row['properties'])) {
                continue;
            }

            $fields = $row['properties']['fields'];
            if (!is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldIndex => $field) {
                if (!is_array($field)) {
                    continue;
                } 

                if (!array_key_exists('properties', $field)) {
                    continue;
                }

                if (!array_key_exists('name', $field['properties'])) {
                    continue;
                }

                $name = Str::between($field['properties']['name'], $containerName . '[', ']');

                if (!array_key_exists($name, $values)) {
                    continue;
                }

                if (!array_key_exists('defaultValue', $field['properties'])) {
                    continue;
                }

                $properties['rows'][$rowIndex]['properties']['fields'][$fieldIndex]['properties']['defaultValue'] = $values[$name];
            }
        }

        return $this->html($properties);
    }
}