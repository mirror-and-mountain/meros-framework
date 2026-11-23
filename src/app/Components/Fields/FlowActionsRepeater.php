<?php

namespace MM\Meros\App\Components\Fields;

use Illuminate\Support\Collection;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Facades\Support\Ajax;

class FlowActionsRepeater extends Repeater {
    private array $inputFields = [];
    private array $lockedSelectors = [];
    private array $dynamicRequired = [];
    private array $dynamicReadonly = [];

    /**
     * Hide the lookup contract in the form builder.
     *
     * @var boolean
     */
    protected static bool $showInFormBuilder = false;

    /**
     * The nonce used for creating a new field.
     *
     * @var string|null
     */
    protected ?string $createFieldNonce = null;

    protected function configure(): void {
        parent::configure();
        $this->view('meros::forms.fields.flow-actions-repeater');

        $this->createFieldNonce = Ajax::getActionNonce('meros_create_field');
        $this->ajaxUrl = Ajax::getUrl();

        $this->setSerializableProperties([
            'createFieldNonce'
        ]);

        $this->setNonPersistableProperties([
            'createFieldNonce'
        ]);
    }

    public function inputField(string $name, Field $field, bool $lockSelector = false, bool $required = false, bool $readonly = false): void {
        $this->inputFields[$name] = $field;
        $this->lockedSelectors[$name] = $lockSelector;
        $this->dynamicRequired[$name] = $required;
        $this->dynamicReadonly[$name] = $readonly;
    }

    protected function resolveTableRowField(Field $field, Collection $rowData, bool $templateRow): Field {
        $name = $rowData->get('input_name');
        if (!$templateRow && $field->getName() === 'input_value' &&
            !$rowData->get('use_dynamic_value') && isset($this->inputFields[$name])) {
            return clone $this->inputFields[$name];
        }

        $clone = parent::resolveTableRowField($field, $rowData, $templateRow);
        if (!$templateRow && $field->getName() === 'use_dynamic_value' && ($this->lockedSelectors[$name] ?? false)) {
            $clone->disabled();
        }
        if (!$templateRow && $field->getName() === 'input_value') {
            if ($this->dynamicRequired[$name] ?? false) $clone->required();
            if ($this->dynamicReadonly[$name] ?? false) $clone->readonly();
        }
        return $clone;
    }

    protected function filterSerializedProperties(array $properties): array {
        $properties = parent::filterSerializedProperties($properties);
        $properties['inputControlTemplates'] = array_map(fn (Field $field) => $field->html(), $this->inputFields);
        return $properties;
    }
}