<?php 

namespace MM\Meros\App\Support;

use Closure;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Contracts\FieldRegistrar;

use MM\Meros\App\Support\RepeaterRow;

abstract class Field extends Feature {
    // Should be defined in concrete classes to specify the WP hook to use when registering via the load() method.
    protected string $hook;

    // The field's unique identifier, used as the 'id' attribute for the field input.
    public string $id = '';

    // The name attribute for the field input
    public string $name = '';

    // The type of the field, which determines the component used to render it.
    public string $type = '';

    // The label for the field, used in the admin UI.
    public string $label = '';

    // The description for the field, used in the admin UI.
    public string $description = '';

    // The current value of the field.
    public mixed $value = null;

    // Additional arguments for the field.
    public array $args = [];

    // Used to store options for 'select' type fields.
    public array $options = [];

    // Indicates whether the select field supports multiple selections.
    public bool $multiSelect = false;

    public function __construct(
        public FeatureProvider $source,
        public FieldRegistrar  $registrar,
        public ?Closure        $callback = null,
    ) {

        $this->id          = $this->registrar->getID() . '_field';
        $this->name        = $this->getFieldName();
        $this->label       = $this->registrar->getLabel();
        $this->description = $this->registrar->getDescription();
        $this->value       = $this->registrar->getValue();

        if ($this->callback === null) {
            $this->callback = $this->convertToClosure([$this, 'render']);
        }

        if (!$this->isInRepeater()) {
            add_action($this->hook, function() {
                $this->load($this);
            });
        }

        $this->addToRegistry();
    }

    /***************************
     * Public Chainable methods
     ***************************/

    public function text(array $args = []): self {
        return $this->type('text', $args);
    }

    public function email(array $args = []): self {
        return $this->type('email', $args);
    }

    public function tel(array $args = []): self {
        return $this->type('tel', $args);
    }

    public function url(array $args = []): self {
        return $this->type('url', $args);
    }

    public function password(array $args = []): self {
        return $this->type('password', $args);
    }

    public function textarea(array $args = []): self {
        return $this->type('textarea', $args);
    }

    public function number(array $args = []): self {
        return $this->type('number', $args);
    }

    public function checkbox(array $args = []): self {
        return $this->type('checkbox', $args);
    }

    public function select(array $options, array $args = []): self {
        $this->options = $options;

        return $this->type('select', $args);
    }

    public function multiselect(array $options, array $args = []): self {
        $this->options = $options;
        $this->multiSelect = true;

        return $this->type('select', $args);
    }

    public function radio(array $options, array $args = []): self {
        $this->options = $options;

        return $this->type('radio', $args);
    }

    public function checkboxes(array $options, array $args = []): self {
        $this->options = $options;
        $this->multiSelect = true;

        return $this->type('checkbox', $args);
    }

    public function repeater(array $args = []): self {
        return $this->type('repeater', $args);
    }

    /**
     * Sets the field type and related properties.
     *
     * @param  string $type The type of the field (e.g., 'text', 'select', etc.).
     * @param  array  $args Additional arguments for the field.
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function type(string $type, array $args = []): self {
        $this->type = $type;
        $this->args = $args;

        $this->setReady();
        return $this;   
    }

    /**
     * Configures the field based on the provided configuration array. This method can be used to set things like options for select fields or other arguments.
     *
     * @param  mixed $config The configuration for the field, which can be an array of arguments or a callback for specific field types.
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function configure(mixed $config): self {
        if (is_array($config)) {
            if (method_exists($this, $this->type)) {
                // e.g. select(), radio(), etc.
                $this->{$this->type}($config);
            } else {
                $this->args = array_merge($this->args, $config);
            }
        }

        return $this;
    }

    /***************************
     * Abstracts & Finals
     ***************************/

    // Should be implemented by concrete field classes to set the 'ready' state based on the field's current configuration.
    abstract protected function setReady(): void;

    abstract protected function load(Feature $instance): void;

    /***************************
     * Rendering
     ***************************/
    public function render(): void {
        if ($this->type === 'repeater') {
            $this->renderRepeater();
            return;
        }

        echo view('meros::admin.field', [
            'component' => $this->getFieldComponent(),
            'field'     => $this
        ]);
    }

    /***************************
     * Repeater Rendering
     ***************************/
    protected function renderRepeater(): void {
        $items = is_array($this->value) && !empty($this->value)
            ? $this->value
            : [[]]; // ensures at least one row

        echo '<div class="meros-repeater">';

        foreach ($items as $index => $row) {
            $rowInstance = new RepeaterRow($this, $index, $row);

            // Always wrap in a row for consistent structure
            $rowInstance->row(function ($r) {

                // Custom layout
                $layout = method_exists($this->registrar, 'getLayout')
                    ? $this->registrar->getLayout()
                    : null;

                if ($layout) {
                    $layout($r);
                    return;
                }

                // Default fallback layout
                $names = $this->registrar->getFieldNames();
                $r->fields($names);
            });
        }

        // Add button for new rows
        $this->renderAddButton();
        echo '</div>';
    }

    /***************************
     * Rendering Helpers
     ***************************/

    /**
     * Renders the "Add Row" button for repeater fields.
     *
     * @return void
     */
    protected function renderAddButton(): void {
        echo '<button type="button" class="button button-primary meros-add-row">Add Row</button>';
    }

    /**
     * Determines the field component to render based on the field type.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return "admin.fields.{$this->type}";
    }

    /**
     * Generates the 'name' attribute for the field input, which is used 
     * to identify the field's value in form submissions.
     *
     * @param  int|null $index Optional index for fields that are part of a repeater or similar structure.
     * 
     * @return string The generated field name.
     */
    public function getFieldName(?int $index = null): string {
        $root = $this->getRootRegistrar();
        $name = $root->getName();

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
     * Traverses up the registrar hierarchy to find the root registrar, which provides the base name for the field.
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

    /**
     * Infers the appropriate field type based on the sub-item's arguments, 
     * defaulting to 'text' if no specific type is defined.
     *
     * @param  [type] $item
     *
     * @return string
     */
    protected function inferFieldType($item): string {
        return match ($item->args['type'] ?? 'string') {
            'string'  => 'text',
            'boolean' => 'checkbox',
            'integer', 'number' => 'number',
            default   => 'text',
        };
    }

    /**
     * Determines if the field is being rendered within a repeater context.
     *
     * @return bool
     */
    protected function isInRepeater(): bool {
        return str_contains($this->registrar->path, '*');
    }
}