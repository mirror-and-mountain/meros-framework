<?php 

namespace MM\Meros\Services;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Feature;
use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\App\Support\Fields\Field;
use MM\Meros\App\Support\Fields\Maker as FieldMaker;

class FieldGroup extends Feature {
    public string $slug = '';
    public string $label = '';
    public string $description = '';

    protected bool   $initialised = false;
    protected string $initError = "FieldGroup must be initialised with make() before setting properties or adding fields.";
    
    /**
     * Field instances that belong to this field group.
     *
     * @var Collection
     */
    public Collection $fields;

    public function __construct(public FeatureProvider $source) {
        $this->fields = collect([]);
    }

    /**
     * Sets the field group as ready (or not) based on its current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->slug)) {
            $this->ready = false;
        }

        $this->ready = true;
    }

    protected function load(Feature $instance): void {
        // No loading for field groups as they are utilised in other hookable features.
    }

    /**
     * Initialises a field group instance with a slug and returns the instance for chaining.
     *
     * @param Closure|string $callbackOrSlug A closure to configure the field group or a string to set the slug.
     *
     * @return self
     */
    public function make(Closure|string $callbackOrSlug = ''): self {
        if ($callbackOrSlug instanceof Closure) {
            $callbackOrSlug($this);
        } 
        
        elseif (is_string($callbackOrSlug)) {
            $this->slug = Str::slug($callbackOrSlug);
        }

        $this->initialised = true;
        $this->setReady();
        return $this;
    }

    /**
     * Adds a field instance to the group's collection of fields. 
     *
     * @param Field|array<Field> $field A single Field instance or an array of Field instances to add to the group.
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before adding fields.
     */
    public function add(Field|array $field): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        if (is_array($field)) {
            $this->fields = $this->fields->merge($field);
        } else {
            $this->fields->push($field);
        }

        return $this;
    }

    /**
     * Creates a new field instance, adds it to the group, and returns it for chaining.
     *
     * @param string       $type The type of field to create.
     * @param Closure|null $callback An optional closure to configure the field instance.
     * @param array        $config Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     * @throws \BadMethodCallException if the field group has not been initialised with make() before adding fields.
     */
    public function new(string $type, ?Closure $callback = null, array $config = []): Field {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $field = new FieldMaker($this->source);
        $field = $field->make($type, $config);

        if ($callback) {
            $callback($field);
        }
        
        $this->add($field);
        return $field;
    }

    /**
     * Retrieves the collection fields in the group.
     *
     * @return Collection
     */
    public function getFields(): Collection {
        return $this->fields;
    }

    /**
     * Sets the slug of the field group.
     *
     * @param string $slug
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function slug(string $slug): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->slug = Str::slug($slug);
        $this->setReady();
        return $this;
    }

    /**
     * Sets the label of the field group.
     *
     * @param string $label
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function label(string $label): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description of the field group.
     *
     * @param string $description
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function description(string $description): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->description = $description;
        return $this;
    }

    /**
     * Determines layout for each field, placing fields in rows to prevent gaps.
     * Returns an array of ['field' => Field, 'span' => int]
     */
    protected function resolveLayout(): array {
        $rows = [];
        $currentRow = [];
        $currentWidth = 0;

        $map = [
            'full' => 6,
            'half' => 3,
            'third' => 2,
        ];

        foreach ($this->fields as $field) {
            // Determine base width
            if (method_exists($field, 'getWidth') && $field->getWidth()) {
                $widthKey = $field->getWidth();
            } else {
                $type = method_exists($field, 'getType') ? $field->getType() : null;
                $fullWidthTypes = ['textarea', 'wysiwyg', 'repeater'];
                $widthKey = in_array($type, $fullWidthTypes) ? 'full' : 'half';
            }

            $span = $map[$widthKey] ?? 3;

            // If adding this field would overflow the row, flush current row first
            if ($currentWidth + $span > 6) {
                $rows[] = $this->normalizeRow($currentRow, $currentWidth);
                $currentRow = [];
                $currentWidth = 0;
            }

            $currentRow[] = [
                'field' => $field,
                'span' => $span,
            ];

            $currentWidth += $span;

            // If row is exactly full, flush it
            if ($currentWidth === 6) {
                $rows[] = $currentRow;
                $currentRow = [];
                $currentWidth = 0;
            }
        }

        // Flush remaining row
        if (!empty($currentRow)) {
            $rows[] = $this->normalizeRow($currentRow, $currentWidth);
        }

        // Flatten rows
        return array_merge(...$rows);
    }

    protected function normalizeRow(array $row, int $currentWidth): array {
        if ($currentWidth >= 6 || empty($row)) {
            return $row;
        }

        $remaining = 6 - $currentWidth;
        $count = count($row);

        // Distribute evenly
        $baseIncrement = intdiv($remaining, $count);
        $extra = $remaining % $count;

        foreach ($row as $index => &$item) {
            $item['span'] += $baseIncrement;

            // Distribute remainder one-by-one
            if ($extra > 0) {
                $item['span'] += 1;
                $extra--;
            }
        }

        return $row;
    }

    public function render(): void {
        echo view('meros::components.fields.wrappers.field-group', [
            'label'       => $this->label,
            'description' => $this->description,
            'fields'      => $this->resolveLayout()
        ]);
    }
}