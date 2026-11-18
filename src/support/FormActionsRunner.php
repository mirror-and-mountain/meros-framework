<?php

namespace MM\Meros\Support;

class FormActionsRunner extends FlowRunner {
    /**
     * Reserved dynamic values for form actions.
     */
    protected const RESERVED_DYNAMIC_VALUES = [...parent::RESERVED_DYNAMIC_VALUES, 'form_id', 'form_response_id'];

    /**
     * The ID of the current form.
     *
     * @var integer
     */
    private int $formId;

    /**
     * The ID of the current form response.
     *
     * @var integer
     */
    private int $formResponseId;

    public function __construct(int $formId, int $formResponseId, array $actions, array $triggerPayload) {
        $this->formId         = $formId;
        $this->formResponseId = $formResponseId;
        parent::__construct($actions, $triggerPayload);
    }

    protected function resolveDynamicValue(string $dynamicValue): mixed {
        $addFormValues = fn (array $values): array => [
            'form_values' => [
                'form_id' => $this->formId,
                'form_response_id' => $this->formResponseId,
            ] + ($values['form_values'] ?? []),
        ] + $values;

        add_filter('meros_dynamic_form_values', $addFormValues, PHP_INT_MAX);
        try {
            return parent::resolveDynamicValue($dynamicValue);
        } finally {
            remove_filter('meros_dynamic_form_values', $addFormValues, PHP_INT_MAX);
        }
    }
}