<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Contracts\Field;

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
     * Human-readable label for the form.
     *
     * @var string
     */
    public string $label = '';

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

    /**
     * Sets the form as ready (or not) based on its current configuration.
     *
     * @return void
     */
    protected function hook(): void {
        if (empty($this->id)) {
            $this->ready = false;
        }

        $this->ready = true;
    }

    protected function load(): void {
        // No loading for forms as they aren't directly hooked into WP.
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
                    $this->elements[$index] = FieldGroupsRegister::checkout($this->provider)->makeFrom($element);
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