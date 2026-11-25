<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;

use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Data\Integrations;
use MM\Meros\Support\EmailHelpers;

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
            description: 'Paragraphs and blank lines are preserved in HTML email and converted to newlines for plain-text email.',
            required: true,
            fieldType: 'richtext',
            outsideRepeater: true,
            fieldCallback: function (Field $field) {
                $field->__suggestDynamicValues();
            }
        );

        EmailHelpers::addRecipientInputs($this);

        $mailers = Integrations::getMailers(true)->where(function (Mailer $mailer) {
            return $mailer->ready();
        });

        $mailersArray = [];

        if ($mailers->isNotEmpty()) {
            $mailersArray = $mailers->mapWithKeys(function (Mailer $mailer) {
                return [$mailer->getName() => $mailer->getLabel()];
            })->toArray();
        }

        $this->addInput(
            'send_using',
            'string',
            label: 'Send Using',
            default: 'default',
            required: true,
            outsideRepeater: true,
            fieldType: 'select',
            fieldCallback: function (Select $field) use ($mailersArray) {
                $field->options([
                    'default' => 'Default',
                    ...$mailersArray
                ]);
            }
        );

        $this->addInput(
            'send_as_plain_text',
            'boolean',
            label: 'Send as Plain Text',
            default: false,
            outsideRepeater: true,
            fieldType: 'checkbox',
            fieldCallback: function (Checkbox $field) {
                $field->toggle(true);
            }
        );
    }

    public function run(array $input): FlowActionResponse {
        $subject = $input['email_subject'] ?? '';
        $body    = $input['email_body'] ?? '';
        $to      = EmailHelpers::getRecipients($input, 'to');
        $cc      = EmailHelpers::getRecipients($input, 'cc');
        $bcc     = EmailHelpers::getRecipients($input, 'bcc');
        $plainText = $input['send_as_plain_text'] ?? false;

        $body = wp_kses_post($body);

        if ($to === null || $cc === null || $bcc === null) {
            return new FlowActionResponse(null, 'Invalid email recipient.');
        }

        if ($subject === '' || $body === '' || empty($to)) {
            return new FlowActionResponse(
                null,
                'Missing required fields'
            );
        }

        $body = EmailHelpers::formatBody($body, $plainText);
        $mailer = $input['send_using'] ?? 'default';

        if ($mailer !== 'default') {
            try {
                $mailer = Integrations::getMailer($mailer);
            } catch (\InvalidArgumentException $e) {
                $mailer = 'default'; // Fallback to default mailer if the specified one is not found or invalid.
            }
        }

        if ($mailer instanceof Mailer && $mailer->ready()) {
            $result = $mailer->sendMail(
                $to,
                $subject,
                $body,
                $cc,
                $bcc,
                !$plainText
            );

            if ($result === false) {
                return new FlowActionResponse(
                    null,
                    'Failed to send email using ' . ($input['send_using'] ?? 'default')
                );
            }
        } else {
            $headers = ['Content-Type: ' . ($plainText ? 'text/plain' : 'text/html') . '; charset=UTF-8'];
            foreach ($cc as $address) {
                $headers[] = 'Cc: ' . $address;
            }
            foreach ($bcc as $address) {
                $headers[] = 'Bcc: ' . $address;
            }

            $result = \wp_mail(
                $to,
                $subject,
                $body,
                $headers
            );

            if (!$result) {
                return new FlowActionResponse(null, 'Failed to send email using WordPress.');
            }
        }

        return new FlowActionResponse(
            'Email sent successfully.'
        );
    }

}