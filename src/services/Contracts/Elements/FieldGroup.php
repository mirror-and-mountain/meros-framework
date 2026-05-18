<?php 

namespace MM\Meros\Services\Contracts\Elements;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\PostMeta;
use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Contracts\Elements\Interfaces\FieldParent;

use MM\Meros\Services\Contracts\Elements\Concerns\CanAttachFields;

use MM\Meros\App\Fields\Repeater;

class FieldGroup extends FeatureDefinition implements FieldParent {
    /**
     * A unique handle for the field group, used for identification and referencing.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * A human-readable title for the field group.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * A description providing additional context about the field group, its purpose, or usage instructions.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The form that this field group belongs to, if any.
     *
     * @var Form|null
     */
    protected ?Form $form = null;

    /**
     * The parent meta object this field group belongs to, if any.
     *
     * @var PostMeta|null
     */
    protected ?PostMeta $parentMetaObject = null;

    /**
     * The HTML tag to use when rendering the field group container.
     *
     * @var string
     */
    protected string $htmlTag = 'div';

    use CanAttachFields {
        CanAttachFields::field as traitField;
    }

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setProps($props);
        $this->instantiateFields();
    }

    /***************************
     * Feature Contract Methods
     ***************************/

    protected function queue(): void {
        // Field groups don't use the queue method.
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
        
        return $this;
    }

    /**
     * Sets the title of the field group.
     *
     * @param string $title
     *
     * @return self
     */
    public function title(string $title): self {
        $this->title = $title;
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

    public function field(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        $field = $this->traitField($fieldIdOrClass, $callback, $props);

        if ($this->parentMetaObject !== null) {
            $subItem = collect($this->parentMetaObject->getSubItems())->where('name', $field->getName())->first();
            
            if ($subItem === null) {
                $dataType = $field->getDataType();
                $newItem  = $this->parentMetaObject->add([
                    'name'  => $field->getName(),
                    'type'  => $dataType,
                ]);

                if ($field instanceof Repeater) {
                    $subFields = $field->getFields();

                    foreach ($subFields as $subField) {
                        $newItem->itemType('object');

                        $subItem = $newItem->add([
                            'name' => $subField->getName(),
                            'type' => $subField->getDataType(),
                        ]);

                        $subItem->field($subField);
                        $subItem->label($subField->getLabel());
                        $subItem->description($subField->getHelpText());

                        $default = $subField->getDefault();

                        if (gettype($default) === $dataType) {
                            $subItem->default($default);
                        }

                        $subField->rootName($subItem->getRootName());
                    }
                }

                $newItem->field($field);
                $newItem->label($field->getLabel());
                $newItem->description($field->getHelpText());

                $default = $field->getDefault();

                if (gettype($default) === $dataType) {
                    $newItem->default($default);
                }

                $field->rootName($newItem->getRootName());
            }
        }

        return $field;
    }

    /**
     * Associates the field group with a form.
     *
     * @param Form $form
     *
     * @return self
     */
    public function form(Form $form): self {
        $this->form = $form;
        $this->htmlTag = 'fieldset'; // Use fieldset when inside a form for better semantics
        return $this;
    }

    /**
     * Associates the field group with a parent meta object.
     *
     * @param PostMeta $meta
     *
     * @return self
     */
    public function parentMeta(PostMeta $meta): self {
        $this->parentMetaObject = $meta;
        return $this;
    }

    /**
     * Attaches the field group to a form and sets the form context on the group.
     *
     * @param Form $form
     *
     * @return self
     */
    public function attachTo(Form $form): self {
        $this->form($form);
        $form->attach($this);
        return $this;
    }

    /**
     * Converts the field group and its fields to an array format suitable for JSON serialization.
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = [
            'handle'      => $this->handle,
            'title'       => $this->title,
            'description' => $this->description,
            'htmlTag'     => $this->htmlTag,
            'fields'      => array_map(fn($field) => $field->toJson(), $this->fields),
        ];

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
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
            'title'       => $this->title,
            'description' => $this->description,
            'fields'      => $this->resolveLayout(),
            'htmlTag'     => $this->htmlTag,
            'formID'      => $this->form ? $this->form->id : null,
        ]);
    }
}