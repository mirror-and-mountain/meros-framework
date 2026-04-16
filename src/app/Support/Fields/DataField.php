<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\FeatureProvider;

use MM\Meros\App\Contracts\FieldRenderer;
use MM\Meros\App\Contracts\DataFieldRegistrar;

use MM\Meros\App\Support\Fields\Concerns\HasFieldProps;

use MM\Meros\App\Support\Settings\Setting;

abstract class DataField implements FieldRenderer {
    use HasFieldProps;

    public function __construct(
        public FeatureProvider    $source,
        public DataFieldRegistrar $registrar
    ) {
        $this->id          = $this->registrar->getFieldID();
        $this->label       = $this->registrar->getFieldLabel();
        $this->description = $this->registrar->getFieldDescription();
        $this->value       = $this->registrar->getValue();

        $this->name = $this->getFieldName();
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
     * @return DataFieldRegistrar
     */
    protected function getRootRegistrar(): DataFieldRegistrar {
        $current = $this->registrar;

        while (method_exists($current, 'hasParent') && $current->hasParent()) {
            $current = $current->parent;
        }

        return $current;
    }
}