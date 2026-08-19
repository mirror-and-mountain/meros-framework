<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Storable;
use MM\Meros\Contracts\Features\Data\DataItem;
use MM\Meros\Contracts\Features\Components\Field;

class PostMeta extends DataItem {

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function whenConfigured(): void {
        parent::whenConfigured();
    }

    // =========================================================================
    // Container Association
    // =========================================================================

    final protected function resolveContainer(): ?Storable {
        return $this->container ?? null;
    }

    /**
     * Sets the associated PostMetaContainer for this PostMeta item.
     *
     * @param Storable $container The PostMetaContainer to associate with this PostMeta item.
     *
     * @return static
     */
    final public function container(Storable $container): static {
        return $this->postMetaContainer($container);
    }

    /**
     * Sets the associated PostMetaContainer for this PostMeta item, ensuring that the provided container is of the correct type.
     *
     * @param PostMetaContainer $container The PostMetaContainer to associate with this PostMeta item.
     *
     * @return static
     */
    private function postMetaContainer(PostMetaContainer $container): static {
        $this->container = $container;
        $this->whenContainerSet();
        return $this;
    }

    // =========================================================================
    // Field Association
    // =========================================================================

    /**
     * Associates a field with this PostMeta item, adding it to its container's FieldGroup instance.
     *
     * @param string        $type             The type of the field to associate.
     * @param Closure|array $callbackOrProps  A closure or array of properties for configuring the field.
     *
     * @return Field The associated Field instance.
     * 
     * @throws \BadMethodCallException If the PostMeta item does not have a name or is not associated with a PostMetaContainer, or if the data type is not compatible with fields.
     * @throws \InvalidArgumentException If the field type is not compatible with the PostMeta item's data type.
     */
    final public function field(?string $type = null, Closure|array $callbackOrProps = []): Field {
        $this->beforeFieldSet();

        if ($this->name === 'placeholder_id') {
            throw new \BadMethodCallException("The PostMeta item '{$this->name}' must have a name before associating a field.");
        }

        $container = $this->resolveContainer();

        if (!($container instanceof PostMetaContainer)) {
            throw new \BadMethodCallException("The PostMeta item '{$this->name}' must be associated with a PostMetaContainer before defining its field.");
        }

        $dataType = $this->getDataType();
        $compatible = $dataType !== 'object';

        if (!$compatible) {
            throw new \BadMethodCallException("The PostMeta item '{$this->name}' of data type '{$dataType}' is not compatible with fields.");
        }

        $fieldType = is_string($type) && !empty($type) ? $type : $this->inferFieldType($dataType);
        $this->field = $container->__field($fieldType, $callbackOrProps);

        if (!$this->field->isCompatibleWithDataType($dataType)) {
            throw new \InvalidArgumentException("The field type '{$fieldType}' is not compatible with the data type '{$dataType}' for PostMeta item '{$this->name}'.");
        }

        // Sync field attributes with this PostMeta item
        $containerName = $container->getName(true);
        $this->field->name($containerName . '[' . $this->name . ']');
        $this->field->id($containerName . '-' . Str::replace('_', '-', $this->name));
        $this->field->label($this->getLabel());
        $this->field->description($this->getDescription());
        $this->field->default($this->getDefault());

        $this->whenFieldSet($this->field);
        return $this->field;
    }

    final public function __addExistingField(Field $field): static {
        $this->field = $field;

        $containerName = $this->resolveContainer()->getName(true);
        $this->field->name($containerName . '[' . $this->name . ']');
        $this->field->id($containerName . '-' . Str::replace('_', '-', $this->name));
        
        $this->whenFieldSet($this->field);
        return $this;
    }

    // =========================================================================
    // Value Setting
    // =========================================================================

    /**
     * Sets the value of the PostMeta item for a specific post ID.
     *
     * @param int   $postId The ID of the post for which to set the value.
     * @param mixed $value  The value to set for the PostMeta item.
     *
     * @return void
     * @throws \BadMethodCallException If the PostMeta item is not associated with a PostMetaContainer.
     */
    final public function setValue(int $postId, mixed $value): void {
        $container = $this->resolveContainer();

        if (!($container instanceof PostMetaContainer)) {
            throw new \BadMethodCallException("The PostMeta item '{$this->name}' must be associated with a PostMetaContainer before setting its value.");
        }

        $container->currentPostId($postId)->setItemValue($this->name, $value);
    }
}