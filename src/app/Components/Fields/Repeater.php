<?php

namespace MM\Meros\App\Components\Fields;

use Closure;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Facades\Components\Fields;

class Repeater extends Field {
    protected bool $hasForm = false;
    protected bool $allowsAdd = true;
    protected bool $allowsRemove = true;

    protected string $addText    = 'Add Row';
    protected string $removeText = 'Remove';
    protected string $formText   = 'Edit';
    protected string $emptyText  = 'No rows added yet.';

    /**
     * An array of field definitions available in the repeater's table view.
     *
     * @var array<Field>
     */
    protected array $tableFields = [];

    /**
     * An array of field definitions available in the repeater's row form view.
     *
     * @var array<Field>
     */
    protected array $formFields  = [];    

    protected function configure(): void {
        $this->type('repeater');
        $this->dataType('array.object');
        $this->setSerializableProperties([
            'emptyText',
            'hasForm',
            'formText',
            'allowsAdd',
            'addText',
            'allowsRemove',
            'removeText', 
            'tableFields', 
            'formFields'
        ]);
    }

    // =========================================================================
    // Attribute Methods
    // =========================================================================

    public function allowAdd(bool $allow = true): static {
        $this->allowsAdd = $allow;
        return $this;
    }

    public function allowRemove(bool $allow = true): static {
        $this->allowsRemove = $allow;
        return $this;
    }

    // =========================================================================
    // Field Management
    // =========================================================================

    /**
     * Adds a field to the repeater's table view.
     *
     * @param string                  $type
     * @param Closure|array|null|null $callbackOrProps
     *
     * @return Field
     * @throws \InvalidArgumentException if the field type is 'repeater', as nested repeaters are not supported in this context.
     */
    public function tableField(string $type, Closure|array|null $callbackOrProps = null): Field {
        if ($type === 'repeater') {
            throw new \InvalidArgumentException("Nested repeaters are not supported in this context.");
        }

        return $this->field($type, $callbackOrProps, $this->tableFields);
    }

    /**
     * Adds a field to the repeater's row form view.
     *
     * @param string                  $type
     * @param Closure|array|null|null $callbackOrProps
     *
     * @return Field
     */
    public function formField(string $type, Closure|array|null $callbackOrProps = null): Field {
        $this->hasForm = true;
        return $this->field($type, $callbackOrProps, $this->formFields);
    }

    /**
     * Internal helper method to create a field and add it to the specified field collection.
     *
     * @param string                  $type
     * @param Closure|array|null|null $callbackOrProps
     * @param array                   $fieldCollection Reference to the field collection to which the new field will be added.
     *
     * @return Field
     */
    private function field(string $type, Closure|array|null $callbackOrProps, array &$fieldCollection): Field {
        $field = Fields::checkout($this)->makeFrom($type, $callbackOrProps);
        $field->repeater($this, $this->id);
        $fieldCollection[] = $field;
        return $field;
    }

    /**
     * Renders the row form for a given row.
     *
     * @return void
     */
    final public function renderRowForm(array $rowData): void {
        $fieldsToShow = array_merge(
            $this->formFields, 
            collect($this->formFields)->where('hiddenInRepeaterForm', false)->all()
        );

        apply_filters('meros_repeater_row_form_fields', $fieldsToShow, $this, $rowData);
        
        foreach ($fieldsToShow as $field) {
            $field->render();
        }
    }

    /**
     * Returns the HTML for the row form for a given row.
     *
     * @param array $rowData The data for the row to render the form for.
     *
     * @return string The HTML of the rendered row form.
     */
    final public function rowFormHtml(array $rowData): string {
        ob_start();
        $this->renderRowForm($rowData);

        $html = ob_get_clean();

        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    protected function resolveFieldView(): string {
        return 'meros::form-components.fields.repeater';
    }
}