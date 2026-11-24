<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Checkbox;

use Illuminate\Support\Facades\Log;

class SendAnEmail extends FlowAction {
    protected function configure(): void {
        $this->name('send_an_email');
        $this->label('Send an Email');

        $this->addInput(
            'email_subject',
            'string',
            label: 'Email Subject',
            required: true,
            outsideRepeater: true
        );

        $this->addInput(
            'email_body',
            'string',
            label: 'Email Body',
            required: true,
            fieldType: 'richtext',
            outsideRepeater: true,
            fieldCallback: function (Field $field) {
                $field->__suggestDynamicValues();
            }
        );

        $recipientTypes = ['to', 'cc', 'bcc'];

        foreach ($recipientTypes as $type) {
            $this->addInput(
                $type . '_recipients',
                'array.object',
                label: strtoupper($type) . ' Recipients',
                description: 'Add ' . strtoupper($type) . ' recipients for the email.',
                required: $type === 'to',
                fieldType: 'repeater',
                fieldCallback: function (Repeater $repeater) {
                    $repeater->addRowText('Add Recipient');
                    $repeater->field('email', function (Field $field) {
                        $field->name('recipient_email_address');
                        $field->label('Recipient Email Address');
                        $field->required();
                        $field->__suggestDynamicValues(['email']);
                    });
                }
            );
        }
    }

    public function run(array $input): FlowActionResponse {
        Log::info('Running SendAnEmail action with input:', $input);
        // Implement the logic to send the email here

        return new FlowActionResponse([
            'success' => true
        ]);
    }
}