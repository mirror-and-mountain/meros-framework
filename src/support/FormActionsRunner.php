<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

class FormActionsRunner extends FlowRunner {
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
        if (Str::startsWith($dynamicValue, 'form_')) {
            switch ($dynamicValue) {
                case 'form_id':
                    return $this->formId;
                case 'form_response_id':
                    return $this->formResponseId;
                default:
                    return null;
            }
        }

        return parent::resolveDynamicValue($dynamicValue);
    }
}