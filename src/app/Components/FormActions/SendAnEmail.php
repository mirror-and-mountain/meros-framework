<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Checkbox;

use MM\Meros\App\Models\FormResponse;

class SendAnEmail extends FlowAction {
    protected function configure(): void {
        $this->name('send_an_email');
        $this->label('Send an Email');

        $this->addInput(
            'to_recipients',
            'array.object',
            label: 'The recipients of the email',
            required: true,
            fieldType: 'repeater',
            fieldCallback: function (Repeater $repeater) {
                $repeater->field('email', function (Field $field) {
                    $field->name('recipient_email_address');
                    $field->label('Recipient Email Address');
                    $field->required();
                    $field->__suggestDynamicValues(['email']);
                });
            }
        );

        $this->addInput(
            'email_body',
            'string',
            label: 'The body of the email',
            required: true,
            fieldType: 'richtext',
            outsideRepeater: true,
            fieldCallback: function (Field $field) {
                $field->__suggestDynamicValues();
            }
        );
    }

    public function run(array $input): FlowActionResponse {
        $emailBody = $input['email_body'] ?? '';

        // Implement the logic to send the email here

        return new FlowActionResponse([
            'success' => true
        ]);
    }
}