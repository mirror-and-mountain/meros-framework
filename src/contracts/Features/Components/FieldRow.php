<?php

namespace MM\Meros\Contracts\Features\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Serializable;

use MM\Meros\Contracts\Features\Concerns\IsSerializable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;
use MM\Meros\Contracts\Features\Concerns\MakesItems;
use MM\Meros\Contracts\Features\Concerns\SanitizesHtml;

final class FieldRow implements Serializable {
    /**
     * The feature provider that created the row.
     *
     * @var FeatureProvider
     */
    private FeatureProvider $provider;

    /**
     * The field row's identifier.
     *
     * @var string
     */
    private string $id = '';

    /**
     * The row's parent Form instance, if any.
     *
     * @var Form|null
     */
    private ?Form $form = null;

    /**
     * The row's parent FieldGroup instance, if any.
     *
     * @var FieldGroup|null
     */
    private ?FieldGroup $parentGroup = null;

    /**
     * The row's child FieldGroup instance, if any.
     *
     * @var FieldGroup|null
     */
    private ?FieldGroup $childGroup = null;

    /**
     * The row's fields.
     *
     * @var array<Field|array|string>
     */
    private array $fields = [];

    /**
     * The maximum number of fields allowed in the row.
     *
     * @var int
     */
    private int $fieldCapacity = 3;

    use IsSerializable, InstantiatesItems, MakesItems, SanitizesHtml;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Constructs a new instance of FieldRow.
     *
     * @param FeatureProvider $provider The feature provider that is creating the row.
     * @param string          $id       The row's id.
     * @param array           $fields   An array of fields to include in the row.
     * @param FieldGroup|null $parentGroup The parent FieldGroup instance, if any.
     */
    private function __construct(
        FeatureProvider $provider, 
        string          $id,
        array           $fields = [],
        ?Form           $form = null,
        ?FieldGroup     $parentGroup = null
    ) {
        $this->provider = $provider;
        $this->id($id); // Ensures the id is slugged and set correctly

        $this->fields      = $fields;
        $this->form        = $form;
        $this->parentGroup = $parentGroup;

        $this->init();
    }

    /**
     * Creates a new instance of FieldRow.
     *
     * @param FeatureProvider  $provider The feature provider that is creating the row.
     * @param array            $fields An array of fields to include in the row.
     * @param Form|null        $form The parent Form instance, if any.
     * @param FieldGroup|null  $parentGroup The parent FieldGroup instance, if any.
     * @param string           $id The row's id.
     * 
     * @return static A new instance of FieldRow.
     */
    public static function make(
        FeatureProvider $provider,
        array           $fields = [],
        ?Form           $form = null,
        ?FieldGroup     $parentGroup = null,
        string          $id = '',
    ): static {
        $id = empty($id) 
            ? 'mforms-row-' . Str::substr(Str::uuid(), 0, 8) 
            : $id;

        return new static($provider, $id, $fields, $form, $parentGroup);
    }

    /**
     * Initialises the FieldRow instance.
     *
     * @return void
     */
    private function init(): void {
        $this->setSerializableProperties(([
            'id',
            'parentGroup',
            'childGroup',
            'fields'
        ]));

        if (!empty($this->fields)) {
            $this->instantiateFields();
        }
    }

    /**
     * Instantiates the fields in the row based on their type.
     *
     * @return void
     * @throws \RuntimeException If a field cannot be instantiated.
     */
    private function instantiateFields(): void {
        $this->fields = array_map(function ($field) {
            if ($field instanceof Field) {
                return $field;
            }

            if (is_array($field)) {
                if (!isset($field['type'])) {
                    throw new \InvalidArgumentException("Field array must contain a 'type' key.");
                }

                $fieldInstance = $this->makeItemFrom($field['type'], Field::class, $field['properties'] ?? []);

                if ($fieldInstance instanceof Field) {
                    $fieldInstance->row($this);
                    return $fieldInstance;
                }

                throw new \RuntimeException("Failed to create a Field instance of type '{$field['type']}'.");
            } 
            
            else if (is_string($field)) {
                $fieldInstance = $this->makeItemFrom($field, Field::class);
                
                if ($fieldInstance instanceof Field) {
                    $fieldInstance->row($this);
                    return $fieldInstance;
                }

                throw new \RuntimeException("Failed to create a Field instance of type '{$field}'.");
            }
        }, $this->fields);
    }

    // =========================================================================
    // Field & FieldGroup Management
    // =========================================================================

    /**
     * Adds a field to the row. If the row has reached its field capacity, a new FieldRow instance is created for the field.
     *
     * @param string        $type The type of the field to add.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     * @param array         $props Additional properties to pass to the field's constructor.
     *
     * @return FieldRow The current FieldRow instance or a new FieldRow instance if a new row was created.
     * @throws \RuntimeException If the field could not be created.
     */
    public function field(string $type, Closure|array $callbackOrProps = [], array $props = []): FieldRow {
        if (count($this->fields) === $this->fieldCapacity) {
            return $this->makeNewRowForField($type, $callbackOrProps);
        }

        $field = $this->makeItemFrom(
            $type, Field::class, 
            $callbackOrProps, 
            array_merge(
                $props, [
                    'form'     => $this->form, 
                    'row'      => $this,
                    'rowIndex' => $this->parentGroup ? $this->parentGroup->getRowIndex($this) : $this->form?->getRowIndex($this),
                    'group'    => $this->parentGroup,
                ]
            ));

        if ($field instanceof Field) {
            $this->fields[] = $field;
            $field->rowPosition($this->getFieldPosition($field));
            return $this;
        }
    
        throw new \RuntimeException("Failed to create a Field instance of type '{$type}'.");
    }

    /**
     * Creates a new FieldRow instance for the specified field type and adds it to the parent Form or FieldGroup.
     *
     * @param string        $type The type of the field to create.
     * @param Closure|array $callbackOrProps A closure or array of properties for the field.
     *
     * @return FieldRow The newly created FieldRow instance.
     */
    private function makeNewRowForField(string $type, Closure|array $callbackOrProps = []): FieldRow {    
        $newRow = FieldRow::make(
            $this->provider, 
            [
                [
                    'type'       => $type,
                    'properties' => $callbackOrProps
                ]
            ],
            $this->form, 
            $this->parentGroup
        );

        if ($this->parentGroup) {
            $this->parentGroup->row($newRow);
        } else if ($this->form) {
            $this->form->row($newRow);
        }

        return $newRow;
    }

    /**
     * Adds a FieldGroup to the row.
     *
     * @param FieldGroup|Closure|string|array $group           The FieldGroup to add, which can be a FieldGroup instance, an array of properties, an existing group id, or a closure that configures the group.
     * @param Closure|array                   $callbackOrProps An optional callback to configure the group or an array of properties to pass to the group's constructor.
     * @param array                           $props           Additional properties to pass to the group's constructor.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function group(FieldGroup|Closure|string|array $group, Closure|array $callbackOrProps = [], array $props = []): static {
        if ($group instanceof FieldGroup) {
            $this->childGroup = $group;
        } 

        else if (is_string($group)) {
            $alias = $group;
            $this->childGroup = $this->makeItemFrom(
                $alias, 
                FieldGroup::class, 
                $callbackOrProps, 
                array_merge(
                    $props, ['form' => $this->form, 'parentGroup' => $this->parentGroup]
                ) 
            );
        }
        
        else if ((is_array($group) || $group instanceof Closure) && is_array($callbackOrProps)) {
            $props = array_merge(
                is_array($group) ? $group : [], 
                $callbackOrProps, ['form' => $this->form]
            );
            $this->childGroup = $this->makeItem(FieldGroup::class, $group, $props);
        }

        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    public function setIdentifier(string $id): static {
        return $this->id($id);
    }
    
    /**
     * Sets the FieldRow's id.
     *
     * @param string $id
     *
     * @return static
     */
    public function id(string $id): static {
        $this->id = Str::slug($id);
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the FieldRow's ID - format is not used.
     *
     * @param string $format
     *
     * @return string
     */
    public function getIdentifier(string $format = 'default'): string {
        return $this->id;
    }

    /**
     * Returns the feature provider that created the row.
     *
     * @return FeatureProvider
     */
    public function getProvider(): FeatureProvider {
        return $this->provider;
    }

    /**
     * Returns the row's child group if one exists.
     *
     * @return FieldGroup|null
     */
    public function getGroup(): ?FieldGroup {
        return $this->childGroup;
    }

    /**
     * Returns whether the FieldRow has capacity for more fields.
     *
     * @return boolean
     */
    public function hasCapacity(): bool {
        return count($this->fields) < $this->fieldCapacity;
    }

    /**
     * Returns whether the FieldRow has capacity for a specified number of slots.
     *
     * @param int $positions The number of slots needed to check for capacity.
     *
     * @return boolean
     */
    public function hasCapacityFor(int $positions): bool {
        if ($this->childGroup) {
            return false;
        }

        $occupiedSpaces = 0;
        foreach ($this->fields as $field) {
            $occupiedSpaces += $field::getRowPositions();
        }

        return $occupiedSpaces + $positions < $this->fieldCapacity;
    }

    /**
     * Returns whether the FieldRow is empty (i.e., has no fields and no child group).
     *
     * @return boolean
     */
    public function isEmpty(): bool {
        return empty($this->fields) && $this->childGroup === null;
    }

    /**
     * Returns the position of the given Field instance within the row's fields.
     *
     * @param Field $field The Field instance to find the position of.
     * @return int|null The position of the Field instance, or null if not found.
     */
    public function getFieldPosition(Field $field): ?int {
        $index = array_search($field, $this->fields, true);
        return $index !== false ? $index : null;
    }

    /**
     * Returns the last Field instance in the row, or null if the row has no fields.
     *
     * @return Field|null The last Field instance, or null if the row is empty.
     */
    public function getLastField(): ?Field {
        return end($this->fields) ?: null;
    }

    /**
     * Returns the Field instance at the specified index in the row's fields.
     *
     * @param int $index The index of the field to retrieve.
     * @return Field|null The Field instance at the specified index, or null if not found.
     */
    public function getField(int $index): ?Field {
        return $this->fields[$index] ?? null;
    }

    /**
     * Returns the fields of the FieldRow instance as an array or a Collection, based on the $collect parameter.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getFields(bool $collect = false): array|Collection {
        if ($this->childGroup === null) {
            return $collect ? collect($this->fields) : $this->fields;
        }

        return $this->childGroup->getFields($collect);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function render(array $properties = [], bool $mergeProperties = false): void {
        $view = 'meros::components.field-row';

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