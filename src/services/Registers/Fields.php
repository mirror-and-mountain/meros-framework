<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Field;
use MM\Meros\Services\Contracts\Register;

use MM\Meros\Services\Contracts\FeatureDefinition;

class Fields extends Register {
    protected string $identifier = 'handle';
    protected string $definition = Field::class;
    protected array  $supports = [
        'register',
        'makeFrom',
        'attach',
        'get',
        'all'
    ];

    /**
     * Parses properties for the asset's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'handle'     => $props['handle'] ?? '',
            'id'         => $props['id'] ?? '',
            'name'       => $props['name'] ?? '',
            'label'      => $props['label'] ?? '',
            'help_text'  => $props['help_text'] ?? '',
            'default'    => $props['default'] ?? null,
            'value'      => $props['value'] ?? null,
            'required'   => $props['required'] ?? false,
            'disabled'   => $props['disabled'] ?? false,
            'classList'  => $props['classList'] ?? [],
            'width'      => $props['width'] ?? 'full',
            'wrapper'    => $props['wrapper'] ?? 'meros::fields.wrappers.default',
        ];
    }

    /**
     * Overrides the attach() method to attach a field instance to the register.
     * Does not require unique instance handles.
     *
     * @param FeatureDefinition $field The field instance to attach.
     *
     * @return FeatureDefinition The attached field instance.
     * @throws \InvalidArgumentException If the provided instance is not a Field.
     */
    public function attach(FeatureDefinition $field): FeatureDefinition {
        $this->ensureCheckedOut();

        if (!$field instanceof Field) {
            throw new \InvalidArgumentException("Only instances of Field can be attached to the Fields register.");
        }

        $this->instances->push($field);

        $this->checkin();
        return $field;
    }
}