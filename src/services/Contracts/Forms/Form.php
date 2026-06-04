<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Contracts\Forms\FormRow;

use MM\Meros\Facades\FormRows as FormRowsRegister;

class Form extends FeatureDefinition {
    /**
     * The form's handle.
     *
     * @var string
     */
    public string $handle = '';
    
    /**
     * The form's ID.
     *
     * @var int|string
     */
    public int|string $id = '';

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
     * The form rows that belong to this form.
     *
     * @var array<FormRow>
     */
    protected array $rows = [];

    /**
     * The actions associated with the form.
     *
     * @var array
     */
    protected array $actions = [];

    /***************************
     * Feature Contract Methods
     ***************************/

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);

        if (empty($this->handle)) {
            $this->handle = Str::snake($this->title);
        }
    }

    protected function queue(): void {
        // Forms don't use the queue method.
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the handle of the form.
     *
     * @param string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = Str::snake($handle);
        return $this;
    }

    /**
     * Sets the ID of the form.
     *
     * @param int|string $id
     *
     * @return self
     */
    public function id(int|string $id): self {
        $this->id = $id;
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
     * Attaches a form row to the form.
     *
     * @param Closure|null $callback Optional. A callback that receives the form row instance for configuration.
     *
     * @return self
     */
    public function row(?Closure $callback = null): self {
        $row = FormRowsRegister::checkout($this->provider)
            ->make();

        if ($callback) {
            $callback($row);
        }

        $this->rows[] = $row;
        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Returns the form rows that belong to this form.
     *
     * @param bool $asArray Whether to return the rows as an array or a collection.
     *
     * @return Collection|array
     */
    public function getRows(bool $asArray = false): Collection|array {
        return $asArray ? $this->rows : collect($this->rows);
    }

    /**
     * Returns the elements contained in the form, which may be either individual fields or child field groups.
     * 
     * @param bool $asArray Whether to return the elements as an array or a collection.
     *
     * @return Collection|array
     */
    public function getElements(bool $asArray = false): Collection|array {
        $elements = collect();

        $this->walkRows(function(FormRow $row) use (&$elements) {
            $elements = $elements->merge($row->getElements());
        });

        return $asArray ? $elements->toArray() : $elements;
    }

    /**
     * Returns the fields contained in the form as a collection or array.
     *
     * @param bool $asArray Whether to return the fields as an array or a collection.
     *
     * @return Collection|array
     */
    public function getFields(bool $asArray = false): Collection|array {
        $fields = collect();

        $this->walkRows(function(FormRow $row) use (&$fields) {
            $fields = $fields->merge($row->getFields());
        });

        return $asArray ? $fields->toArray() : $fields;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Walks through each form row in the form and applies a callback function.
     *
     * @param Closure $callback A callback function that accepts a FormRow instance as its parameter.
     *
     * @return void
     */
    protected function walkRows(Closure $callback): void {
        foreach ($this->rows as $row) {
            if ($row instanceof FormRow) {
                $callback($row);
            }
        }
    }

    /**
     * Returns the form's data as an array or JSON string.
     *
     * @param boolean $asString
     * @param string  ...$flags
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = [
            'handle'      => $this->handle,
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'schema'      => [
                'actions' => $this->actions,
                'rows'    => array_map(fn($row) => $row->toJson(), $this->rows)
            ],
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
     * Renders the field group and its fields using a Blade view.
     *
     * @return void
     */
    public function render(): void {
        echo view('meros::fields.field-group', [
            'title'       => $this->title,
            'description' => $this->description,
        ]);
    }
}