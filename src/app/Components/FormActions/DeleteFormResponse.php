<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\App\Models\FormResponse;

final class DeleteFormResponse extends FlowAction {
    protected function configure(): void {
        $this->name('store_response');
        $this->label('Store Response');
        $this->addInput(
            'response_id',
            'integer',
            label: 'Form Response ID',
            required: true,
            default: 'form_response_id',
            readonly: true,
            dynamic: true
        );
    }

    public function run(array $input): FlowActionResponse {
        $responseId = $input['response_id'];
        $formResponse = FormResponse::find($responseId);

        if ($formResponse !== null) {
            $formResponse->delete();
            return new FlowActionResponse([
                'form_response_id' => $responseId
            ]);
        }

        return new FlowActionResponse(null, 'Failed to delete response');
    }
}