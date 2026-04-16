<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Contracts\FieldRegistrar;

use MM\Meros\App\Support\Settings\Setting;

abstract class Field {
    public string $id = '';
    public string $name = '';
    public string $label = '';
    public string $description = '';
    public mixed  $value = null;
    public array  $classList = [];

    public bool $required = false;
    public bool $disabled = false;
    protected array $config = [];

    public function __construct(
        public FeatureProvider $source,
        public FieldRegistrar  $registrar
    ) {
        $this->id          = $this->registrar->getFieldID();
        $this->label       = $this->registrar->getFieldLabel();
        $this->description = $this->registrar->getFieldDescription();
        $this->value       = $this->registrar->getValue();

        $this->name = $this->getFieldName();
    }

    // Apply configuration to the field instance.
    // This method can be overridden by child classes to handle specific configurations.
    public function configure(array $config): self {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    // Make the field required
    public function required(bool $required = true): self {
        $this->required = $required;
        return $this;
    }

    // Disable the field
    public function disabled(bool $disabled = true): self {
        $this->disabled = $disabled;
        return $this;
    }

    // Add CSS classes to the field
    public function class(string|array $classes): self {
        $classes = is_array($classes) ? $classes : explode(',', $classes);
        $this->classList = array_merge($this->classList, $classes);
        return $this;
    }

    // Render the field
    public function render(): void {
        $view = 'meros::components.fields.wrappers.admin-field';

        if ($this->registrar instanceof Setting) {
            $view = 'meros::components.fields.wrappers.setting-field';
        }

        echo view($view, [
            'component' => $this->getFieldComponent(),
            'field'     => $this
        ]);
    }

    /***************************
     * Helpers
     ***************************/

    // Each field type will specify its own component for rendering.
    abstract public function getFieldComponent(): string;

    /**
     * Retrieves all child fields associated with the field's registrar.
     *
     * @return array An array of Field instances that are children of this field.
     */
    protected function getChildFields(): array {
        return array_filter(
            $this->registrar->subItems,
            fn($item) => $item->field !== null
        );
    }

    /**
     * Generates the 'name' attribute for the field input.
     * 
     * @param  int|null $index Optional index for fields that are part of a repeater or similar structure.
     * 
     * @return string
     */
    public function getFieldName(?int $index = null): string {
        $root = $this->getRootRegistrar();
        $name = $root->getFieldName();

        $segments = explode('.', $this->registrar->path);

        // Remove root from segments
        array_shift($segments);

        foreach ($segments as $segment) {
            if ($segment === '*') {
                $segment = $index ?? 0;
            }

            $name .= "[{$segment}]";
        }

        return $name;
    }

    /**
     * Traverses up the registrar hierarchy to find the root registrar.
     *
     * @return FieldRegistrar
     */
    protected function getRootRegistrar(): FieldRegistrar {
        $current = $this->registrar;

        while (method_exists($current, 'hasParent') && $current->hasParent()) {
            $current = $current->parent;
        }

        return $current;
    }
}