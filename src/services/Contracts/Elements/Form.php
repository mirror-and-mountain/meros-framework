<?php 

namespace MM\Meros\Services\Contracts\Elements;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Facades\Fields as FieldsRegister;
use MM\Meros\Facades\FieldGroups as FieldGroupsRegister;

class Form extends FeatureDefinition {
    /**
     * The form's ID.
     *
     * @var string
     */
    public string $id = '';

    /**
     * Human-readable title for the form.
     *
     * @var string
     */
    public string $title = '';

    /**
     * A description of the form's purpose or usage.
     *
     * @var string
     */
    public string $description = '';

    /**
     * The handle of the FieldStyle to use when rendering fields in this form.
     *
     * @var string
     */
    protected string $style = 'nice';

    /**
     * The URL where the form will be submitted to.
     *
     * @var string
     */
    protected string $action = '';

    /**
     * The HTTP method to use when submitting the form.
     *
     * @var string
     */
    protected string $method = 'POST';

    /**
     * The elements of the form.
     *
     * @var array<FieldGroup|Field>
     */
    protected array $elements = [];

    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setProps($props);
        $this->instantiateElements();
    }

    /***************************
     * Feature Contract Methods
     ***************************/

    protected function queue(): void {
        // Forms don't use the queue method.
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the ID of the form.
     *
     * @param string $id
     *
     * @return self
     */
    public function id(string $id): self {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the title of the form.
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
     * Sets the description of the form.
     *
     * @param string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the action URL of the form.
     *
     * @param string $action
     *
     * @return self
     */
    public function action(string $action): self {
        $this->action = $action;
        return $this;
    }

    /**
     * Sets the HTTP method of the form.
     *
     * @param string $method
     *
     * @return self
     */
    public function method(string $method): self {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Sets the FieldStyle to use when rendering fields in this form.
     *
     * @param string $handle The handle of the FieldStyle to use for this form's fields.
     *
     * @return self
     */
    public function style(string $handle): self {
        $this->style = $handle;

        $this->walkElements(function ($element) use ($handle) {
            $element->style($handle);
        });

        return $this;
    }

    /**
     * Attaches a field group or field to the form.
     *
     * @param FieldGroup|Field|array $element The element(s) to attach.
     *
     * @return self
     */
    public function attach(FieldGroup|Field|array $element): self {
        if (is_array($element)) {
            $this->elements = array_merge($this->elements, $element);

            $this->walkElements(function($element) {
                $element->style($this->style);

                if ($element instanceof FieldGroup) {
                    $element->form($this);
                }
            });
        }

        else {
            $element->style($this->style);

            if ($element instanceof FieldGroup) {
                $element->form($this);
            }

            $this->elements[] = $element;
        }

        return $this;
    }

    /**
     * Creates a field group from the given class or ID and attaches it to the form.
     *
      * @param string                  $groupClassOrId The class name or ID of the group to create and attach.
      * @param Closure|array|null|null $callback        Optional callback or properties array for configuring the group.
      * @param array                   $props           Optional properties for the group if no callback is provided.
      *
      * @return FieldGroup The created and attached FieldGroup instance.
      */
    public function group(string $groupClassOrId, Closure|array|null $callback = null, array $props = []): FieldGroup {
        if (is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $group = FieldGroupsRegister::checkout($this->provider)->makeFrom($groupClassOrId, $props);

        if ($callback instanceof Closure) {
            $callback($group);
        }

        $this->attach($group);
        return $group;
    }

    /**
     * Creates a field from the given class or ID and attaches it to the form.
     *
      * @param string                  $fieldClassOrId The class name or ID of the field to create and attach.
      * @param Closure|array|null|null $callback        Optional callback or properties array for configuring the field.
      * @param array                   $props           Optional properties for the field if no callback is provided.
      *
      * @return Field The created and attached Field instance.
      */
    public function field(string $fieldClassOrId, Closure|array|null $callback = null, array $props = []): Field {
        if (is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $field = FieldsRegister::checkout($this->provider)->makeFrom($fieldClassOrId, $props);

        if ($callback instanceof Closure) {
            $callback($field);
        }

        $this->attach($field);
        return $field;
    }

    /**
     * Converts the form and its elements to an array format suitable for JSON serialization.
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'elements'    => array_map(fn($element) => array_merge(
                ['type' => $element instanceof FieldGroup ? 'group' : 'field'],
                $element->toJson()
            ), $this->elements),
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
     * Renders the form and its fields using a Blade view.
     *
     * @return void
     */
    public function render(): void {
        echo view('meros::fields.form', [
            'id'          => $this->id,
            'action'      => $this->action,
            'method'      => $this->method,
            'title'       => $this->title,
            'description' => $this->description,
            'elements'    => $this->elements
        ]);
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Instantiates any elements in the form that are currently represented as class name strings.
     *
     * @return void
     */
    protected function instantiateElements(): void {
        foreach ($this->elements as $index => $element) {
            if (is_string($element)) {
                // Instantiate the element if it's a string (class name)
                if (is_subclass_of($element, FieldGroup::class)) {
                    $group = FieldGroupsRegister::checkout($this->provider)->makeFrom($element);
                    $this->elements[$index] = $group;
                    $group->form($this); // Set the form on the group for proper rendering context
                } else {
                    $this->elements[$index] = FieldsRegister::checkout($this->provider)->makeFrom($element);
                }
            }
        }
    }

    /**
     * Gets the form's elements as a collection.
     *
     * @return Collection
     */
    protected function getElements(): Collection {
        return collect($this->elements);
    }

    /**
     * Walks over the form's elements and applies a callback to each.
     *
     * @param Closure $callback
     *
     * @return void
     */
    protected function walkElements(Closure $callback): void {
        foreach ($this->elements as $element) {
            $callback($element);
        }
    }
}