<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\PostMeta;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;

use MM\Meros\App\Fields\Repeater;
use MM\Meros\Facades\FormRows as FormRowsRegister;

use MM\Meros\Facades\PostTypes;
use MM\Meros\Facades\Context;

class FieldGroup extends FeatureDefinition {
    /**
     * A unique handle for the field group, used for identification and referencing.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The field group's ID.
     *
     * @var int|string
     */
    public int|string $id = '';

    /**
     * A human-readable title for the field group.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * A description providing additional context about the field group, its purpose, or usage instructions.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The rows that belong to this field group.
     *
     * @var array<FormRow>
     */
    protected array $rows = [];

    /**
     * The parent meta object this field group belongs to, if any.
     *
     * @var PostMeta|null
     */
    protected ?PostMeta $parentMetaObject = null;
    

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
        // Field groups don't use the queue method.
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the handle of the field group.
     *
     * @param string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = Str::slug($handle);
        
        return $this;
    }

    /**
     * Alias for handle() method to set the handle of the field group.
     *
     * @param string $name
     *
     * @return self
     */
    public function name (string $name): self {
        return $this->handle($name);
    }

    /**
     * Sets the ID of the field group.
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
     * Sets the title of the field group.
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
     * Sets the description of the field group.
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
     * Attaches a form row to the field group.
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

    public function attach(string|array $postTypes): self {
        $postTypes = (array)$postTypes;

        foreach ($postTypes as $postType) {
            $postTypeInstance = PostTypes::get($postType);

            if ($postTypeInstance) {
                $postTypeInstance->fields($this);
            }
        }

        return $this;
    }

    /**
     * Attaches a field to the field group by creating a new form row for it. 
     * If the field group is associated with a parent meta object, the field 
     * will also be added as a sub-item to the meta object.
     *
     * @param Field|string       $field    A Field instance or a registered field identifier to attach to the group.
     * @param Closure|array|null $callback Optional callback to configure the field if a registered identifier is provided.
     * @param array              $props    Optional properties for the field if a registered identifier is provided.
     *
     * @return Field
     */
    public function field(Field|string $field, Closure|array|null $callback = null, array $props = []): Field {    
        $newRow = null;
        $params = func_num_args();

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $rowIndex = isset($props['row']) ? (int)$props['row'] : null;

        $props['rowIndex'] = $rowIndex; // Store the row index in props for potential use in callbacks
        unset($props['row']); // Remove 'row' from props to avoid confusion

        if ($rowIndex !== null && array_key_exists($rowIndex, $this->rows)) {
            $row   = $this->rows[$rowIndex];
            $field = $row->field($field, $callback, $props);
        } 
        
        else {
            $newRow = FormRowsRegister::checkout($this->provider)->make();
            $field  = $newRow->field($field, $callback, $props);
        }

        if ($newRow !== null) {
            $this->rows[] = $newRow;
        }

        if ($this->parentMetaObject !== null) {
            $field = $this->addMetaField($field);
        }

        return $field;
    }

    /**
     * Creates a new sub-item within the parent meta object matching the field's name and data type,
     * and associates the field with that sub-item. 
     * 
     * If the field group is not associated with a parent meta object, 
     * the field is returned without modification.
     *
     * @param Field $field
     *
     * @return Field
     */
    public function addMetaField(Field $field, bool $addAsNew = true): Field {
        if ($this->parentMetaObject === null) {
             return $field;
        }

        $subItem = collect($this->parentMetaObject->getSubItems())
            ->where('name', $field->getName(false))->first();
            
        if ($subItem === null) {
            $dataType = $field->getDataType();
            $newItem  = $this->parentMetaObject->add([
                'name'  => $field->getName(),
                'type'  => $dataType,
            ]);

            if ($field instanceof Repeater) {
                $subFields = $field->getFields();

                foreach ($subFields as $subField) {
                    $newItem->itemType('object');

                    $subItem = $newItem->add([
                        'name' => $subField->getName(),
                        'type' => $subField->getDataType(),
                    ]);

                    $subItem->field($subField);
                    $subItem->label($subField->getLabel());
                    $subItem->description($subField->getHelpText());

                    $default = $subField->getDefault();

                    if (gettype($default) === $dataType) {
                        $subItem->default($default);
                    }

                    $subField->rootName($subItem->getRootName());
                }
            }

            if ($addAsNew) {
                $newItem->field($field);
            }
            
            $newItem->label($field->getLabel());
            $newItem->description($field->getHelpText());

            $default = $field->getDefault();

            if (gettype($default) === $dataType) {
                $newItem->default($default);
            }

            $field->rootName($newItem->getRootName());
        }

        return $field;
    }

    /**
     * Associates the field group with a parent meta object.
     *
     * @param PostMeta $meta
     *
     * @return self
     */
    public function parentMeta(PostMeta $meta): self {
        $this->parentMetaObject = $meta;
        return $this;
    }

    /**
     * Converts the field group and its fields to an array format suitable for JSON serialization.
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
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
                'rows' => array_map(fn($row) => $row->toJson(), $this->rows)
            ]
        ];

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }


    /***************************
     * Getters
     ***************************/

    /**
    * Retrieves the fields contained within the field group as a collection or array.
    * This includes fields from all rows within the group.
    *
    * @param bool $asArray Whether to return the fields as an array or a collection.
    *
    * @return Collection|array
    */
    public function getFields($asArray = false): Collection|array {
        $fields = collect([]);

        $this->walkRows(function(FormRow $row) use (&$fields) {
            $fields = $fields->merge($row->getFields());
        });

        return $asArray ? $fields->toArray() : $fields;
    }


    /**
     * Retrieves the form rows contained within the field group as a collection or array.
     *
     * @param bool $asArray Whether to return the rows as an array or a collection.
     *
     * @return Collection|array
     */
    public function getRows(bool $asArray = false): Collection|array {
        return $asArray ? $this->rows : collect($this->rows);
    }

    /**
     * Retrieves the handle of the field group.
     *
     * @return string
     */
    public function getHandle(): string {
        return $this->handle;
    }

    /**
     * Retrieves the ID of the field group.
     *
     * @return int|string
     */
    public function getId(): int|string {
        return $this->id;
    }

    /**
     * Retrieves the title of the field group.
     *
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * Retrieves the description of the field group.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /***************************
     * Helpers
     ***************************/

    protected function walkRows(Closure $callback): void {
        foreach ($this->rows as $row) {
            $callback($row);
        }
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
        $classList = 'meros-form-group';

        if (Context::isAdmin()) {
            $classList .= ' meros-form-group--admin';
        }

        echo view('meros::forms.field-group', [
            'groupID'          => $this->id,
            'groupHandle'      => $this->handle,
            'groupTitle'       => Context::isEditingPost() ? '': $this->title,
            'groupDescription' => $this->description,
            'groupRows'        => $this->rows,
            'classList'        => $classList,
        ]);
    }

    /**
     * Renders the field group and its fields, and returns the HTML as a string.
     *
     * @return string
     */
    public function html(): string {
        ob_start();
        $this->render();
        return ob_get_clean();
    }
}