<?php

namespace MM\Meros\Contracts\Features\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Contracts\Concerns\UsesAjax;
use MM\Meros\Contracts\Features\Components\Concerns\IsFormComponent;
use MM\Meros\Contracts\Features\Components\Concerns\MakesFieldRows;

use MM\Meros\Facades\Components\Fields;

class Form extends Feature implements FormComponent, Makeable {
    /**
     * The form's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The form's name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The form's title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The form's description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The view used to render the form.
     *
     * @var string
     */
    private string $view = 'meros::forms.form';

    /**
     * The text shown on the form's submit button.
     *
     * @var string
     */
    protected string $submitText = 'Submit';

    /**
     * Text that can be shown when the form is submitted but is invalid.
     *
     * @var string
     */
    protected string $invalidText = "The form is invalid. Please check the information you've entered.";

    /**
     * The callback function to be executed when the form is submitted.
     *
     * @var Closure|string|null
     */
    protected Closure|string|null $onSubmit = null;

    /**
     * Whether to hide the submit button for the form.
     *
     * @var bool
     */
    protected bool $hideSubmitButton = false;

    use IsFormComponent,
        IsMakeable,
        MakesFieldRows,
        InstantiatesItems,
        UsesAjax;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('id', 'slug');

        $this->setSerializableProperties(([
            'id',
            'name',
            'title',
            'description',
            'attributeString',
            'rows',
            'ajaxUrl',
            'ajaxNonce',
            'submitText',
            'invalidText',
            'onSubmit',
            'hideSubmitButton'
        ]));

        // Need to update this bit...
        $defaultIdentifier = 'mforms-' . Str::substr(Str::uuid(), 0, 8);
        $this->id($defaultIdentifier);
        $this->name(Str::replace('-', '_', $defaultIdentifier));
    }

    final protected function whenConfigured(): void {
        if (!empty($this->rows)) {
            $this->instantiateRows();
        } else {
            // Create an initial row if none are provided
            $this->makeNewRow();
        }

        if (is_string($this->onSubmit)) {
            return;
        }

        $this->initAjax('meros_handle_form_submission_' . $this->name, function (array $postData) {
            $data = json_decode(stripslashes($postData['form_data'] ?? '{}'), true);
            $this->handleFormSubmission($data);

            wp_send_json_success(['message' => 'Form submitted successfully.']);
        });
    }

    /**
     * Handles the form submission by executing the onSubmit callback if it exists and is a Closure.
     * May be overridden in subclasses to provide custom form submission handling logic.
     *
     * @param array $data
     *
     * @return void
     */
    protected function handleFormSubmission(array $data): void {
        if ($this->onSubmit instanceof Closure) {
            call_user_func($this->onSubmit, $data);
        }
    }

    // =========================================================================
    // Row, Field & Group Management
    // =========================================================================

    /**
     * Adds a field to the FieldGroup instance. If the last row has reached its field capacity, a new FieldRow instance is created for the field.
     *
     * @param string        $type The type of the field to add.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     * @param bool          $autoRow Whether to automatically add the field to an existing row if it has capacity (true) or always create a new row for the field (false). Defaults to true.
     *
     * @return static Returns the current instance for method chaining.
     * @throws \RuntimeException If the field could not be created.
     */
    final public function field(string $type, Closure|array $callbackOrProps = [], bool $autoRow = true): static {
        $fieldClass = Fields::getRegisteredFeatureClass($type);
        
        if ($fieldClass === null) {
            throw new \RuntimeException("Field type '{$type}' is not registered.");
        }

        $lastRow = $this->getLastRow(true);

        if ($autoRow) {
            $fieldWidth     = $fieldClass::getRowPositions();
            $rowHasCapacity = $lastRow->hasCapacityFor($fieldWidth);

            if ($rowHasCapacity) {
                $lastRow->field($type, $callbackOrProps);
                return $this;
            }
        }

        if ($lastRow->isEmpty()) {
            $lastRow->field($type, $callbackOrProps);
            return $this;
        }

        return $this->row(function ($row) use ($type, $callbackOrProps) {
            $row->field($type, $callbackOrProps);
        });
    }

    /**
     * Adds a FieldGroup to the form. If the last row has reached its field capacity, a new FieldRow instance is created for the group.
     *
     * @param FieldGroup|Closure|string|array $section         The FieldGroup to add, which can be a FieldGroup instance, an array of properties, an existing group id, or a closure that configures the group.
     * @param Closure|array                   $callbackOrProps An optional callback to configure the group or an array of properties to pass to the group's constructor.
     *
     * @return static Returns the current instance for method chaining.
     */
    final public function section(FieldGroup|Closure|string|array $section, Closure|array $callbackOrProps = []): static {
        $row = $this->getLastRow(true);
        $rowHasCapacity = $row->hasCapacityFor(3);

        if (!$rowHasCapacity) {
            $row = $this->makeNewRow();
        }
        
        $row->group($section, $callbackOrProps);
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the form's id.
     *
     * @param string $id
     *
     * @return static
     */
    final public function id(string $id): static {
        $id = $this->setIdentifier($id);

        if (empty($this->name) || Str::startsWith($this->name, 'mforms_')) {
            $this->name = Str::replace('-', '_', $id);   
        }

        return $this;
    }

    /**
     * Sets the form's name.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $this->name = Str::snake($name);
        return $this;
    }

    /**
     * Sets the form's title.
     *
     * @param string $title
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the callback function to be executed when the form is submitted. If a string is provided, 
     * it will be used as the name of a client-side event which will be triggered instead of a server-side callback.
     * 
     * Event names are prefixed with 'mforms::' to avoid conflicts with other events. Example:
     * mforms::myCustomEvent.
     *
     * @param Closure|string $callback
     *
     * @return static
     */
    final public function onSubmit(Closure|string $callback): static {
        $this->onSubmit = $callback;
        return $this;
    }

    /**
     * Sets whether to hide the submit button for the form.
     *
     * @param bool $hide Whether to hide the submit button. Defaults to true.
     *
     * @return static
     */
    final public function hideSubmitButton(bool $hide = true): static {
        $this->hideSubmitButton = $hide;
        return $this;
    }

    /**
     * Sets the text shown on the form's submit button.
     *
     * @param string $text
     *
     * @return static
     */
    final public function submitText(string $text): static {
        $this->submitText = $text;
        return $this;
    }

    /**
     * Sets the text shown when the form's inputs are invalid.
     *
     * @param string $text
     *
     * @return static
     */
    final public function invalidText(string $text): static {
        $this->invalidText = $text;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the form's id.
     * 
     * @param string $format The format of the identifier to return. Defaults to 'default'.
     *
     * @return string
     */
    final public function getId(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Returns the form's name.
     *
     * @return string
     */
    final public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the form's title.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    /**
     * Returns the form's rows.
     *
     * @return array
     */
    final public function getRows(): array {
        return $this->rows;
    }

    /**
     * Returns the index of the given FieldRow instance within the form's rows.
     *
     * @param FieldRow $row The FieldRow instance to find the index of.
     * @return int|null The index of the FieldRow instance, or null if not found.
     */
    final public function getRowIndex(FieldRow $row): ?int {
        $index = array_search($row, $this->rows, true);
        return $index !== false ? $index : null;
    }

    /**
     * Returns the onSubmit callback if it is a string (JavaScript function name), or null if it is a Closure.
     *
     * @return string|null
     */
    final public function getOnSubmit(): string|null {
        if ($this->onSubmit instanceof Closure) {
            return null;
        }

        return !empty($this->onSubmit) ? $this->onSubmit : null;
    }

    /**
     * Returns all fields from the component's rows.
     * 
     * @param boolean $collect Whether to return the fields as a Collection. If false, returns as an array.
     *
     * @return array|Collection
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
                $this->filterSerializedProperties($this->toArray()),
                $properties
            );
        } 
        
        else {
            $properties = empty($properties) 
                ? $this->filterSerializedProperties($this->toArray())
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
}