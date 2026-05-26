<?php 

namespace MM\Meros\App\Fields;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Elements\Field;
use MM\Meros\Services\Contracts\Elements\Interfaces\FieldParent;

use MM\Meros\Services\Contracts\Elements\Concerns\CanAttachFields;

use MM\Meros\Facades\Context;

class Repeater extends Field implements FieldParent {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'repeater';

    /**
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $category = 'specialised';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'table';
    
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

    use CanAttachFields;

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
        $wrapper = $this->resolveStyle();

        echo view($wrapper, [
            'view'      => $this->getFieldComponent(),
            'field'     => $this,
            'rows'      => $this->buildRows(),
            'showLabel' => $showLabel,
            'showHelp'  => $showHelp
        ]);
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return Context::isAdmin() ? 'meros::fields.repeater-admin' : 'meros::fields.repeater';
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
            : [];

        $rows = [];

        foreach ($items as $index => $rowData) {
            $rowData = is_array($rowData) ? $rowData : [];
            $rowToken = $this->resolveRowToken($rowData, $index);
            $row = [];

            foreach ($this->fields as $field) {
                $fieldInstance = clone $field;
                
                // Store the original field name before generating the indexed name
                $baseFieldName = $fieldInstance->getName();

                $fieldInstance->attribute('data-row-index', $rowToken);
                $fieldInstance->attribute('data-base-field-name', $baseFieldName);

                $fieldInstance->id($this->generateSubFieldId($fieldInstance, $rowToken));
                $fieldInstance->name($this->generateSubFieldName($fieldInstance, $rowToken));
                
                // Look up value using the base field name, not the generated indexed name
                $fieldInstance->value($rowData[$baseFieldName] ?? null);

                // Key the row by base field name so repeater view can access with getFieldNames()
                $row[$baseFieldName] = $fieldInstance;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Builds a hidden template row for the empty-state repeater UI.
     *
     * @return array
     */
    public function buildTemplateRow(): array {
        $rows = [];
        $rowToken = $this->resolveRowToken([], 0);

        foreach ($this->fields as $field) {
            $fieldInstance = clone $field;
            $baseFieldName = $fieldInstance->getName();

            $fieldInstance->attribute('data-row-index', $rowToken);
            $fieldInstance->attribute('data-base-field-name', $baseFieldName);

            $fieldInstance->id($this->generateSubFieldId($fieldInstance, $rowToken));
            $fieldInstance->name($this->generateSubFieldName($fieldInstance, $rowToken));

            $rows[$baseFieldName] = $fieldInstance;
        }

        return $rows;
    }

    /********************
     * Helpers
     ********************/
    /**
     * Generates a unique name for a sub-field based on the repeater's root name, the repeater's name, the row index, and the sub-field's name.
     *
     * @param Field $field The sub-field for which to generate the name.
     * @param string $rowToken Stable row token.
     *
     * @return string The generated sub-field name.
     */
    protected function generateSubFieldName(Field $field, string $rowToken): string {
        $fieldName = $field->getName();

        if ($this->rootName === '') {
            return "{$this->name}[{$rowToken}][{$fieldName}]";
        }

        return "{$this->rootName}[{$this->name}][{$rowToken}][{$fieldName}]";
    }

    /**
     * Generates a unique ID for a sub-field based on the repeater's root name, the repeater's ID, the row index, and the sub-field's ID.
     *
     * @param Field $field The sub-field for which to generate the ID.
     * @param string $rowToken Stable row token.
     *
     * @return string The generated sub-field ID.
     */
    protected function generateSubFieldId(Field $field, string $rowToken): string {
        $fieldId = Str::replace(['[', ']'], '_', $field->getId());
        $idToken = preg_replace('/[^A-Za-z0-9_-]/', '_', $rowToken) ?? $rowToken;

        if ($this->rootName === '') {
            return "{$this->id}_{$idToken}_{$fieldId}";
        }

        return "{$this->rootName}_{$this->id}_{$idToken}_{$fieldId}";
    }

    /**
     * Resolve the repeater row token used for generated sub-field names/ids.
     * Uses a stable row key in admin contexts to avoid radio group churn when reordering.
     */
    protected function resolveRowToken(array $rowData, int $index): string {
        if (Context::isAdmin()) {
            $rowKey = $rowData['__rowKey'] ?? null;

            if (is_string($rowKey) && $rowKey !== '') {
                return $rowKey;
            }
        }

        return (string) $index;
    }
}