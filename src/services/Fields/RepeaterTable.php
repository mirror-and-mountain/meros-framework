<?php 

namespace MM\Meros\App\Support\Fields;

use Closure;
use MM\Meros\App\FeatureProvider;

class RepeaterTable extends Field {
    /**
     * Field definitions for each sub-item within the repeater field.
     *
     * @var array<Field>
     */
    protected array $subFields = [];

    public function __construct(
        public FeatureProvider $source, 
        array $subFields = []
    ) {
        $this->subFields = $subFields;
    }

    /**
     * Renders the repeater table field.
     *
     * @return void
     */
    public function render(): void {
        $view = 'meros::components.fields.wrappers.repeater';

        echo view($view, [
            'rows'  => $this->buildRows(),
            'field' => $this
        ]);
    }

    /**
     * Adds a sub-field or multiple sub-fields to the repeater field.
     *
     * @param Field|array<Field> $field A single Field instance or an array of Field instances to add as sub-fields.
     *
     * @return self
     */
    public function add(Field|array $field): self {
        if (is_array($field)) {
            $this->subFields = array_merge($this->subFields, $field);
        } else {
            $this->subFields[] = $field;
        }

        return $this;
    }

    /**
     * Creates a new sub-field instance and adds it to the repeater field.
     *
     * @param string        $type     The type of field to create (e.g., 'text', 'select').
     * @param Closure|null  $callback An optional closure to configure the new field instance.
     * @param array         $config   Additional configuration for the field creation.
     *
     * @return Field The newly created and added Field instance.
     */
    public function new(string $type, ?Closure $callback = null, array $config = []): Field {
        $field = app(Maker::class, ['source' => $this->source])->make($type, $config);

        if ($callback) {
            $callback($field);
        }
        
        $this->add($field);

        return $field;
    }

    /**
     * Builds row arrays of cloned sub-fields for each repeater item.
     *
     * @return array<int, array<string, Field>>
     */
    protected function buildRows(): array {
        $items = is_array($this->value) && !empty($this->value)
            ? $this->value
            : [[]];

        $rows = [];

        foreach ($items as $index => $rowData) {
            $rowData = is_array($rowData) ? $rowData : [];
            $row = [];

            foreach ($this->subFields as $field) {
                $fieldName = $field->getName();

                $fieldInstance = clone $field;

                $fieldInstance->id = $fieldInstance->id
                    ? "{$fieldInstance->id}_{$index}"
                    : "{$fieldName}_{$index}";

                $fieldInstance->name  = "{$this->name}[{$index}][{$fieldName}]";
                $fieldInstance->value = $rowData[$fieldName] ?? null;

                $row[$fieldName] = $fieldInstance;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Retrieves the names of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldNames(): array {
        return collect($this->subFields)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Retrieves the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldLabels(): array {
        return collect($this->subFields)
            ->pluck('label')
            ->toArray();
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::components.fields.repeater';
    }
}