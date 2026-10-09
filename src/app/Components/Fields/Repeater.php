<?php

namespace MM\Meros\App\Components\Fields;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Components\Fields;

use MM\Meros\Facades\Support\Ajax;

class Repeater extends Field {
    /**
     * Indicates whether the repeater allows adding new rows.
     *
     * @var bool
     */
    protected bool $allowsAdd = true;

    /**
     * Indicates whether the repeater allows removing rows.
     *
     * @var bool
     */
    protected bool $allowsRemove = true;

    /**
     * Indicates whether the repeater allows reordering rows.
     *
     * @var bool
     */
    protected bool $allowsReorder = true;

    /**
     * The text for the "Add Row" button in the repeater.
     *
     * @var string
     */
    protected string $addText = 'Add Row';

    /**
     * The text for the "Remove Row" button in the repeater.
     *
     * @var string
     */
    protected string $removeText = 'Remove';

    /**
     * The text for the "Edit Row" button in the repeater.
     *
     * @var string
     */
    protected string $formText = 'Edit';

    /**
     * The text to display when the repeater has no rows.
     *
     * @var string
     */
    protected string $emptyText = 'Nothing added yet.';

    /**
     * The JavaScript callback function to be executed when the repeater is initialized.
     *
     * @var string
     */
    protected string $onInit = '';

    /**
     * The JavaScript callback function to be executed when a row is removed from the repeater.
     *
     * @var string
     */
    protected string $onRemove = '';

    /**
     * The edit form instance for the repeater, if defined.
     *
     * @var Form|null
     */
    protected ?Form $editForm = null;

    /**
     * The callback function to generate the edit form for a repeater row, if defined.
     *
     * @var Closure|null
     */
    protected ?Closure $editFormCallback = null;

    /**
     * The number of row positions the field occupies in a row (maximum of 3).
     *
     * @var int
     */
    protected static int $occupiesRowPositions = 3;

    /**
     * An array of field definitions available in the repeater's row form view.
     *
     * @var array
     */
    protected array $fields  = [];

    /**
     * An array of field definitions available in a row's configuration form.
     *
     * @var array
     */
    protected array $formFields = [];

    /**
     * The maximum number of rows allowed in the repeater.
     * 0 indicates no limit.
     *
     * @var int
     */
    protected int $maxRows = 0;

    /**
     * The action name for the edit form ajax callback.
     *
     * @var string|null
     */
    private ?string $ajaxUrl = null;

    /**
     * The ajax nonce used for opening the repeater's edit form if applicable.
     *
     * @var string|null
     */
    private ?string $ajaxNonce = null;

    /**
     * The ajax action name for the repeater's edit form.
     *
     * @var string
     */
    protected string $ajaxAction = '';

    /**
     * Whether to hide the repeater field's label.
     *
     * @var boolean
     */
    protected bool $hideLabel = false;

    protected function configure(): void {
        $this->view('meros::forms.fields.repeater');
        $this->wrapper('site', '');
        $this->wrapper('settings', '');
        $this->fieldSet(true);
        $this->type('repeater');
        $this->dataType('array.object');
        $this->setSerializableProperties([
            'emptyText',
            'formText',
            'allowsAdd',
            'addText',
            'allowsRemove',
            'removeText',
            'allowsReorder',
            'editForm',
            'onInit',
            'onRemove',
            'fields',
            'ajaxUrl',
            'ajaxNonce',
            'hideLabel'
        ]);

        $this->setNonPersistableProperties([
            'ajaxUrl',
            'ajaxNonce',
        ]);
    }

    protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->instantiateFields();

        if ($this->hasEditForm()) {
            $this->registerEditFormAjax();
        }
    }

    /**
     * Instantiates any repeater fields added as an array definition.
     *
     * @return void
     */
    private function instantiateFields(): void {
        $this->fields = array_map(function ($field) {
            if ($field instanceof Field) {
                return $field;
            }

            if (is_array($field) && array_key_exists('type', $field)) {
                return $this->field($field['type'], $field, false);
            }
        }, $this->fields);
    }

    // =========================================================================
    // Ajax Handling
    // =========================================================================

    public function __clone(): void {
        if ($this->editForm instanceof Form) {
            $this->editForm = clone $this->editForm;
        }
    }

    public function getAjaxUrl(): ?string {
        return $this->ajaxUrl;
    }

    public function getAjaxNonce(): ?string {
        return $this->ajaxNonce;
    }

    private function registerEditFormAjax(): void {
        $this->ajaxUrl = Ajax::getUrl();
        $this->ajaxNonce = Ajax::getActionNonce('meros_open_repeater_edit_form');
    }

    // =========================================================================
    // Attribute Methods
    // =========================================================================

    /**
     * Sets the field to hide it's label.
     *
     * @param boolean $hide
     *
     * @return static
     */
    public function hideLabel(bool $hide = true): static {
        $this->hideLabel = $hide;
        return $this;
    }

    /**
     * Sets whether the repeater allows adding new rows.
     *
     * @param boolean $allow
     *
     * @return static
     */
    public function allowAdd(bool $allow = true): static {
        $this->allowsAdd = $allow;
        return $this;
    }

    /**
     * Sets the text for the "Add Row" button in the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function addRowText(string $text): static {
        $this->addText = $text;
        $this->allowAdd(true);
        return $this;
    }

    /**
     * Sets whether the repeater allows removing rows.
     *
     * @param boolean $allow
     *
     * @return static
     */
    public function allowRemove(bool $allow = true): static {
        $this->allowsRemove = $allow;
        return $this;
    }

    /**
     * Sets the text for the "Remove Row" button in the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function removeRowText(string $text): static {
        $this->removeText = $text;
        $this->allowRemove(true);
        return $this;
    }

    /**
     * Sets the JavaScript callback function to be executed when the repeater is initialized.
     *
     * @param string $jsCallback
     *
     * @return static
     */
    public function onInit(string $jsCallback): static {
        $this->onInit = $jsCallback;
        return $this;
    }

    /**
     * Sets the JavaScript callback function to be executed when a row is removed from the repeater.
     *
     * @param string $jsCallback
     *
     * @return static
     */
    public function onRemove(string $jsCallback): static {
        $this->onRemove = $jsCallback;
        return $this;
    }

    /**
     * Sets whether the repeater allows reordering rows.
     *
     * @param boolean $allow
     *
     * @return static
     */
    public function allowReorder(bool $allow = true): static {
        $this->allowsReorder = $allow;
        return $this;
    }

    /**
     * Sets the maximum number of rows allowed in the repeater.
     *
     * @param integer $max
     *
     * @return static
     */
    public function maxRows(int $max): static {
        $this->maxRows = $max;
        $this->attribute('data-max-rows', $max);
        return $this;
    }

    /**
     * Sets the text to display when the repeater has no rows.
     *
     * @param string $text
     *
     * @return static
     */
    public function emptyText(string $text): static {
        $this->emptyText = $text;
        return $this;
    }

    /**
     * Sets the callback function to generate the edit form for a repeater row.
     *
     * @param Closure $callback
     * @param string  $buttonText
     *
     * @return static
     */
    public function editForm(Closure $callback, string $buttonText = ''): static {
        $this->editFormCallback = $callback;

        if (!empty($buttonText)) {
            $this->formText = $buttonText;
        }

        $this->editForm = Forms::checkout($this->getProvider())->make(function (Form $form) {
            $form->id("{$this->id}-edit-form");
            $form->name("{$this->name}_edit_form");
            $form->onSubmit('meros_repeater_form_submit');
            $form->hideSubmitButton(true);
            $form->attribute('data-repeater-edit-form', 'true');
        });

        $this->field('hidden', function ($field) {
            $field->name('__form_data');
            $field->label('Form Data');
        });

        return $this;
    }

    /**
     * Sets the text for the "Edit Row" button in the repeater.
     *
     * @param string $text
     *
     * @return static
     */
    public function editRowText(string $text): static {
        $this->formText = $text;
        return $this;
    }

    /**
     * Updates the repeater's edit form instance if it exists.
     *
     * @return void
     */
    protected function whenNameSet(): void {
        if ($this->hasEditForm()) {
            $newName = $this->getName();
            // $newAction = $this->getEditFormAjaxAction();

            $this->editForm->name("{$newName}_edit_form");

            // if ($this->registeredEditFormAction === $newAction) {
            //     return;
            // }

            // if ($this->registeredEditFormAction !== '') {
            //     $this->removeAjax($this->registeredEditFormAction);
            //     $this->registeredEditFormAction = '';
            // }

            // if ($this->editFormAjaxConfigured) {
            //     $this->registerEditFormAjax();
            // }
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Checks if the repeater has an edit form defined.
     *
     * @return boolean
     */
    public function hasEditForm(): bool {
        if ($this->hasForm()) {
            if ($this->getForm()->hasAttribute('data-repeater-edit-form')) {
                return false;
            }
        }

        return $this->editFormCallback !== null && $this->editForm instanceof Form;
    }

    // =========================================================================
    // Field Management
    // =========================================================================

    /**
     * Adds a field to the repeater's table view.
     *
     * @param string             $type
     * @param Closure|array|null $callbackOrProps
     * @param bool               $add Whether to add the field to the repeater's fields array.
     *
     * @return Field
     * @throws \InvalidArgumentException if the field type is 'repeater', as nested repeaters are not supported in this context.
     */
    public function field(string $type, Closure|array|null $callbackOrProps = null, bool $add = true): Field {
        if ($type === 'repeater') {
            throw new \InvalidArgumentException("Nested repeaters are not supported in this context.");
        }

        return $this->addField($type, $callbackOrProps, $this->fields, $add);
    }

    /**
     * Retrieves the fields defined for the repeater's table view.
     *
     * @param bool $collect If true, returns a Collection; otherwise, returns an array.
     *
     * @return array|Collection The fields defined for the repeater's table view.
     */
    public function getFields(bool $collect = false): array|Collection {
        if ($collect) {
            return collect($this->fields);
        }

        $fields = [];

        collect($this->fields)->each(function ($field, $index) use (&$fields) {
            $fields[] = $field->toArray();
        });

        return $fields;
    }

    /**
     * Internal helper method to create a field and add it to the specified field collection.
     *
     * @param string                  $type
     * @param Closure|array|null|null $callbackOrProps
     * @param array                   $fieldCollection Reference to the field collection to which the new field will be added.
     * @param bool                    $add             Whether to add the field to the repeater's fields array.
     *
     * @return Field
     */
    private function addField(string $type, Closure|array|null $callbackOrProps, array &$fieldCollection, bool $add = true): Field {
        $field = Fields::checkout($this->getProvider())->makeFrom($type, $callbackOrProps);
        $field->repeater($this, $this->id);

        if ($add) {
            $fieldCollection[] = $field;
        }

        return $field;
    }

    // =========================================================================
    // Edit Form Rendering
    // =========================================================================

    public function renderEditForm(array $rowData = []): string {
        if ($this->hasEditForm() === false) {
            return 'Sorry, no edit form is defined for this repeater.';
        }

        $formData = $rowData['form_data'] ?? [];

        $form = call_user_func(
            $this->editFormCallback, 
            $this->editForm, 
            $rowData,
            $formData
        );

        if ($form instanceof Form) {
            $fields = $form->getFields(true);
            $fields->each(function ($field) use ($rowData, $formData) {
                $name = $field->getName();

                $inTable = array_key_exists($name, $rowData);
                $inForm  = array_key_exists($name, $formData);

                if ($inForm) {
                    $field->default($formData[$name]);
                }

                if ($inTable) {
                    $field->repeater(null);
                    $field->default($rowData[$name]);
                }
            });

            return $form->html();
        }

        return 'Something went wrong while rendering the edit form.';
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Ensures that the 'type' attribute is set to 'text' if it hasn't been set already.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function filterSerializedProperties(array $properties): array {
        $properties['tableRows'] = $this->buildTableRows();
        return $properties;
    }

    protected function filterStorageProperties(array $properties): array {
        unset($properties['tableRows']);
        return parent::filterStorageProperties($properties);
    }

    private function buildTableRows(): array {
        $value = $this->getDefaultValue();
        $items = is_array($value) ? $value : [];
        $rows = [];

        // Add a template row
        $rows[-1] = $this->buildTableRow(-1, collect([]), true);

        foreach ($items as $index => $rowData) {
            if ($index === -1) {
                continue;
            }

            $rows[$index] = $this->buildTableRow($index, collect($rowData));
        }

        return $rows;
    }

    private function buildTableRow(int $index, Collection $rowData, bool $templateRow = false): array {
        $row = [];
        
        foreach ($this->fields as $field) {
            $clone = clone $field;

            $name  = $clone->getName();
            $id    = "{$this->id}-{$clone->getId()}";
            $value = $templateRow ? $clone->getDefaultValue() : $rowData->get($name);

            // Hydrates lookups
            if ($field->handle === 'lookup' && !$templateRow) {
                $field->default($value);
                $value = $field->getDefaultValue();
            }

            if ($templateRow) {
                $clone->attribute('data-repeater-template-row', 'true');
                $name = $name . '__template';
                $id   = $id . '-template';
            }

            $clone->name("{$this->name}[{$index}][{$name}]");
            $clone->id($templateRow ? "{$id}-row-template" : "{$id}-row-{$index}");
            $clone->default($value);
            $clone->attribute('data-repeater-row-index', $index);
            $clone->attribute('data-repeater-field-name', $name);

            $row[] = $clone->toArray();
        }

        return $row;
    } 

}