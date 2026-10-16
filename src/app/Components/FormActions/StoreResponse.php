<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Components\FormAction;
use MM\Meros\Contracts\Features\Components\FormActionResponse;

use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\App\Models\FormResponse;
use MM\Meros\Contracts\Features\Components\FormActionInputSchema;

final class StoreResponse extends FormAction {
    protected function configure(): void {
        $this->name('store_response');
        $this->label('Store Response');
    }

    public static function getFormBuilderControls(
        FieldGroup $controlGroup, 
        array $rowData,
        array $repeaterData,
        array $formFields
    ): FieldGroup {
        $controlGroup->field('text', [
            'name' => 'test_control_field',
            'label' => 'Test Control Field'
        ]);

        return $controlGroup;
    }

    public function run(int $formId, array $configuration, array $input): FormActionResponse {
        FormResponse::create([
            'form_id'  => $formId,
            'response' => $input['data'] ?? [],
            'status'   => 'complete'
        ]);

        return new FormActionResponse($this, true);
    }
}