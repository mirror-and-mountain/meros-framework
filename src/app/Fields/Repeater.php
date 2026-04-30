<?php 

namespace MM\Meros\App\Fields;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\Interfaces\FieldParent;

use MM\Meros\Services\Contracts\Elements\Concerns\CanAttachFields;

class Repeater extends Field implements FieldParent {
    /**
     * The root name for the repeater field, used to generate sub-field names.
     *
     * @var string
     */
    protected string $rootName = '';

    /**
     * Default class list for repeaters.
     *
     * @var array
     */
    protected array $classList = ['meros-repeater-field'];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'array.object'
    ];

    /**
     * Whether to force the field to take up the full width of its container, regardless of the width setting.
     *
     * @var bool
     */
    protected bool $forceFullWidth = true;

    use CanAttachFields {
        style as public fieldStyle;
    }

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson();

        $json['fields'] = array_map(function($field) {
            return $field->toJson();
        }, $this->fields);
        
        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    /***************************
     * Rendering
     ***************************/

    /**
     * Renders the repeater table field.
     * 
     * @param bool $showLabel Whether to show the field's label in the wrapper. Some styles may ignore this and always show the label, or never show the label.
     * @param bool $showHelp Whether to show the field's help text in the wrapper. Some styles may ignore this and always show the help text, or never show the help text.
     *
     * @return void
     */
    public function render(bool $showLabel = true, bool $showHelp = true): void {
        $view = $this->resolveStyle();

        echo view($view, [
            'view'      => $this->getFieldComponent(),
            'field'     => $this,
            'rows'      => $this->buildRows(),
            'showLabel' => $showLabel,
            'showHelp'  => $showHelp,
        ]);
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::fields.repeater';
    }

    /**
     * Builds row arrays of cloned sub-fields for each repeater item.
     *
     * @return array
     */
    public function buildRows(): array {
        $value = $this->getValue();
        $items = is_array($value) && !empty($value)
            ? $value
            : [[]];

        $rows = [];

        foreach ($items as $index => $rowData) {
            $rowData = is_array($rowData) ? $rowData : [];
            $row = [];

            foreach ($this->fields as $field) {
                $fieldInstance = clone $field;

                $fieldInstance->id($this->generateSubFieldId($fieldInstance, $index));
                $fieldInstance->name($this->generateSubFieldName($fieldInstance, $index));
                $fieldInstance->value($rowData[$fieldInstance->getName()] ?? null);

                $row[$fieldInstance->getName()] = $fieldInstance;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Sets the style used to render the field. Overrides the Field method to apply the style to all sub-fields as well.
     *
     * @param string $style The handle of the FieldStyle
     *
     * @return self
     */
    public function style(string $style): self {
        $this->style = $style;
        $this->fieldStyle($style);
        return $this;
    }

    /********************
     * Helpers
     ********************/
    /**
     * Generates a unique name for a sub-field based on the repeater's root name, the repeater's name, the row index, and the sub-field's name.
     *
     * @param Field $field The sub-field for which to generate the name.
     * @param int $index The index of the repeater row.
     *
     * @return string The generated sub-field name.
     */
    protected function generateSubFieldName(Field $field, int $index): string {
        $fieldName = $field->getName();

        if ($this->rootName === '') {
            return "{$this->name}[{$index}][{$fieldName}]";
        }

        return "{$this->rootName}[{$this->name}][{$index}][{$fieldName}]";
    }

    /**
     * Generates a unique ID for a sub-field based on the repeater's root name, the repeater's ID, the row index, and the sub-field's ID.
     *
     * @param Field $field The sub-field for which to generate the ID.
     * @param int $index The index of the repeater row.
     *
     * @return string The generated sub-field ID.
     */
    protected function generateSubFieldId(Field $field, int $index): string {
        $fieldId = Str::replace(['[', ']'], '_', $field->getId());

        if ($this->rootName === '') {
            return "{$this->id}_{$index}_{$fieldId}";
        }

        return "{$this->rootName}_{$this->id}_{$index}_{$fieldId}";
    }
}