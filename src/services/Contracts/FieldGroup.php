<?php 

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FieldParent;
use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\Services\Concerns\CanAttachFields;

class FieldGroup extends FeatureDefinition implements FieldParent {
    /**
     * A unique handle for the field group, used for identification and referencing.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * A human-readable label for the field group, used in the UI to identify the group of fields.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * A description providing additional context about the field group, its purpose, or usage instructions.
     *
     * @var string
     */
    protected string $description = '';

    use CanAttachFields;

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setProps($props);
        $this->instantiateFields();
    }

    /**
     * Sets the field group as ready (or not) based on its current configuration.
     *
     * @return void
     */
    protected function hook(): void {
        if (empty($this->handle)) {
            $this->ready = false;
        }

        $this->ready = true;
    }

    protected function load(): void {
        // No loading for field groups as they aren't directly hooked into WP.
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the handle of the field group.
     *
     * @param string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = Str::slug($handle);
        
        $this->hook();
        return $this;
    }

    /**
     * Sets the label of the field group.
     *
     * @param string $label
     *
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description of the field group.
     *
     * @param string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /***************************
     * Rendering
     ***************************/

    /**
     * Determines layout for each field, placing fields in rows to prevent gaps.
     * Returns an array of ['field' => Field, 'span' => int]
     * 
     * @return array An array of fields with their corresponding span values for layout.
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

    /**
     * Normalizes a row of fields to ensure it fills the full width by adjusting span values.
     *
     * @param array $row The current row of fields with their span values.
     * @param int $currentWidth The total width currently occupied by the row.
     *
     * @return array The normalized row with adjusted span values.
     */
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

    /**
     * Renders the field group and its fields using a Blade view.
     *
     * @return void
     */
    public function render(): void {
        echo view('meros::fields.field-group', [
            'label'       => $this->label,
            'description' => $this->description,
            'fields'      => $this->resolveLayout()
        ]);
    }
}