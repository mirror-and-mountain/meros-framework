<?php 

namespace MM\Meros\App\Support;

use Closure;

/**
 * Generates the HTML for a single row within a repeater field.
 */
class RepeaterRow {
    public function __construct(
        protected Field $repeater,
        protected int   $index,
        protected array $rowData
    ) {}

    public function field(string $name): void {
        $subItem = collect($this->repeater->registrar->subItems)
            ->firstWhere('name', $name);

        if (!$subItem || !$subItem->field) {
            return;
        }

        $field = clone $subItem->field;

        $field->id    = "{$field->id}_{$this->index}";
        $field->name  = $field->getFieldName($this->index);
        $field->value = data_get($this->rowData, $name);

        echo view('meros::admin.field', [
            'component' => $field->getFieldComponent(),
            'field'     => $field,
        ]);
    }

    public function row(Closure $callback): void {
        $this->startRow();
        $callback($this);
        $this->endRow();
    }

    public function columns(int $count, Closure $callback): void {
        echo "<div class='meros-columns columns-{$count}'>";
        $callback($this);
        echo "</div>";
    }

    public function fields(array $names): void {
        foreach ($names as $name) {
            $this->field($name);
        }
    }

    public function startRow(): void {
        echo '<div class="meros-repeater-row">';
    }

    public function endRow(): void {
        echo '</div>';
    }

    public function removeButton(): void {
        echo '<button type="button" class="button meros-remove-row">Remove</button>';
    }
}