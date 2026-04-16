<?php 

namespace MM\Meros\App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Fields\Field;

class FieldGroup extends Feature {
    public string $slug = '';
    public string $label = '';
    public string $description = '';

    protected bool   $initialised = false;
    protected string $initError = "FieldGroup must be initialised with make() before setting properties or adding fields.";
    
    /**
     * Field instances that belong to this field group.
     *
     * @var Collection<Field>
     */
    public Collection $fields;

    public function __construct(public FeatureProvider $source) {
        $this->fields = collect([]);
        $this->source->registry()->add('fieldGroups', $this);
    }

    /**
     * Sets the field group as ready (or not) based on its current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->slug)) {
            $this->ready = false;
        }

        $this->ready = true;
    }

    protected function load(Feature $instance): void {
        // No loading for field groups as they are utilised in other hookable features.
    }

    /**
     * Initialises a field group instance with a slug and returns the instance for chaining.
     *
     * @param string $slug
     *
     * @return self
     */
    public function make(string $slug = ''): self {
        $this->slug = Str::slug($slug);

        $this->initialised = true;
        $this->setReady();
        return $this;
    }

    /**
     * Adds a field instance to the group's collection.
     *
     * @param Field $field
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before adding fields.
     */
    public function add(Field $field): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->fields->push($field);
        return $this;
    }

    /**
     * Retrieves the collection fields in the group.
     *
     * @return Collection
     */
    public function getFields(): Collection {
        return $this->fields;
    }

    /**
     * Sets the slug of the field group.
     *
     * @param string $slug
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function slug(string $slug): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->slug = Str::slug($slug);
        $this->setReady();
        return $this;
    }

    /**
     * Sets the label of the field group.
     *
     * @param string $label
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function label(string $label): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description of the field group.
     *
     * @param string $description
     *
     * @return self
     * @throws \BadMethodCallException if the field group has not been initialised with make() before setting properties.
     */
    public function description(string $description): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->description = $description;
        return $this;
    }
}