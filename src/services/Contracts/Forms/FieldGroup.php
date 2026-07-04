<?php 

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Livewire\Wireable;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\PostMeta;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FormRow;

use MM\Meros\App\Fields\Repeater;
use MM\Meros\Facades\FormRows as FormRowsRegister;

use MM\Meros\Facades\PostTypes;
use MM\Meros\Facades\Context;
use MM\Meros\Facades\Framework;

class FieldGroup extends FeatureDefinition implements Wireable {
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
    public string $title = '';

    /**
     * A description providing additional context about the field group, its purpose, or usage instructions.
     *
     * @var string
     */
    public string $description = '';

    /**
     * The parent form row this field group belongs to, if any.
     *
     * @var FormRow|null
     */
    public ?FormRow $parentRow = null;

    /**
     * The index of the parent row this field group belongs to, if any.
     *
     * @var int|null
     */
    public ?int $parentRowIndex = null;

    /**
     * The rows that belong to this field group.
     *
     * @var array<FormRow|array>
     */
    public array $rows = [];

    /**
     * The parent meta object this field group belongs to, if any.
     *
     * @var PostMeta|null
     */
    protected ?PostMeta $parentMetaObject = null;

    use Concerns\SanitizesHtml;
    
    // =========================================================================
    // Contract Methods
    // =========================================================================

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);

        if (empty($this->handle)) {
            $this->handle = Str::snake($this->title);
        }

        if (empty($this->id)) {
            $this->id = 'field-group-' . Str::substr(Str::uuid(), 0, 8);
        }

        $this->instantiateRows();
    }

    protected function queue(): void {
        // Field groups don't use the queue method.
    }

    /**
     * Converts the field group instance into a format suitable for Livewire rendering.
     *
     * @return array
     */
    public function toLivewire(): array {
        return $this->toJson();
    }

    /**
     * Reconstructs a field group instance from Livewire data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromLivewire($data): self {
        return new static(
            Framework::get(),
            $data
        );
    }

    /**
     * Alias for fromLivewire() to initialize a field group instance from an array of data.
     *
     * @param array $data
     *
     * @return self
     */
    public static function initFromData(array $data): self {
        return self::fromLivewire($data);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Renders the field group and its fields using a Blade view.
     *
     * @return void
     */
    public function render(array $config = []): void {
        $classList = 'meros-form-group';

        $showTitle       = (bool) ($config['showTitle'] ?? true);
        $showDescription = (bool) ($config['showDescription'] ?? true);
        $classList       = (string) ($config['class'] ?? '') . ' ' . $classList;

        if (Context::isAdmin()) {
            $classList .= ' meros-form-group--admin';
            $showTitle = false;
        }

        echo view('meros::forms.field-group', [
            'groupID'          => $this->id,
            'groupHandle'      => $this->handle,
            'groupTitle'       => $showTitle ? $this->title : '',
            'groupDescription' => $showDescription ? $this->description : '',
            'groupRows'        => $this->rows,
            'classList'        => $classList,
        ]);
    }

    /**
     * Renders the field group and its fields, and returns the HTML as a string.
     *
     * @return string
     */
    public function html(array $config = []): string {
        ob_start();
        $this->render($config);

        $html = ob_get_clean();
        return $this->sanitizeHtml(is_string($html) ? $html : '');
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

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
    public function name(string $name): self {
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
     * @param Closure|FormRow|null $rowOrCallback A callback that receives the form row instance for configuration, or a FormRow instance to attach directly.
     * @param int|null             $rowIndex       Optional index to insert the row at. If not provided, the row will be appended to the end of the rows array.
     *
     * @return self
     */
    public function row(FormRow|Closure|null $rowOrCallback = null, ?int $rowIndex = null): self {
        $index    = $rowIndex ?? count($this->rows);
        $row      = null;
        $callback = null;

        if ($rowOrCallback instanceof FormRow) {
            $row = $rowOrCallback;
        } 
        
        else {
            $row = FormRowsRegister::checkout($this->provider)
                ->make(['index' => $index]);

             $callback = $rowOrCallback;

             if ($callback && $callback instanceof Closure) {
                $callback($row);
             }
        }
        
        array_splice($this->rows, $index, 0, [$row]);

        foreach ($this->rows as $idx => $existingRow) {
            $existingRow->parentGroup($this, (string) $this->id);
            $existingRow->updateIndex($idx);
        }

        return $this;
    }

    /**
     * Attaches the field group to one or more post types, making it available for use within those post types' edit screens.
     *
     * @param string|array $postTypes A single post type or an array of post types to attach the field group to.
     *
     * @return self
     */
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
        $params = func_num_args();

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $rowIndex = isset($props['row']) ? (int)$props['row'] : null;

        $props['rowIndex'] = $rowIndex; // Store the row index in props for potential use in callbacks
        unset($props['row']); // Remove 'row' from props to avoid confusion

        if ($rowIndex !== null && array_key_exists($rowIndex, $this->rows)) {
            $row = $this->rows[$rowIndex];
            $field = $row->field($field, $callback, $props);
        } else {
            $newRow = FormRowsRegister::checkout($this->provider)->make();
            $this->row($newRow);

            $targetIndex = count($this->rows) - 1;
            $field = $this->rows[$targetIndex]->field($field, $callback, array_merge($props, [
                'rowIndex' => $targetIndex,
            ]));
        }

        if ($this->parentMetaObject !== null) {
            // The field is already attached to this group via row->field(); only sync meta schema.
            $field = $this->addMetaField($field, false);
        }

        $field->group($this, $this->id);
        return $field;
    }

    /**
     * Associates the field group with a parent form row, or disassociates it if null is passed. 
     *
     * @param FormRow|null $row
     * @param integer|null $rowIndex
     *
     * @return self
     */
    public function parentRow(?FormRow $row, ?int $rowIndex = null): self {
        $this->parentRow = $row;
        $this->parentRowIndex = $rowIndex;
        return $this;
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

                    // Keep repeater sub-fields scoped to the repeater UI; only register their schema metadata.
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
            'rowIndex'    => $this->parentRowIndex,
            'rows'        => array_map(fn($row) => $row->toJson(), $this->rows)
        ];

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }


    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Checks if the field group contains any form rows.
     *
     * @return bool
     */
    public function hasContent(): bool {
        return count($this->rows) > 0;
    }

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

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Walks through each form row in the field group and applies the given callback function to it.
     *
     * @param Closure $callback
     *
     * @return void
     */
    protected function walkRows(Closure $callback): void {
        foreach ($this->rows as $row) {
            $callback($row);
        }
    }

    /**
     * Instantiates any form rows in the field group that are provided in array format.
     *
     * @return void
     */
    protected function instantiateRows(): void {
        if (empty($this->rows)) {
            return;
        }

        foreach ($this->rows as $index => $rowData) {
            $row = $rowData;

            if (!$rowData instanceof FormRow) {
                $row = FormRow::initFromData($rowData);
            }

            $row->parentGroup($this, (string) $this->id);
            $row->updateIndex($index);

            $this->rows[$index] = $row;
        }
    }
}