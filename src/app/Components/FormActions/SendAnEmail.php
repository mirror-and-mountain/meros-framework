<?php

namespace MM\Meros\App\Components\FormActions;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Automation\FlowActionResponse;

use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Data\Integrations;

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

        $mailers = Integrations::getMailers(true);
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
            required: true,
            outsideRepeater: true,
            fieldType: 'checkbox'
        );
    }

    public function run(array $input): FlowActionResponse {
        $subject = $input['email_subject'] ?? '';
        $body    = $input['email_body'] ?? '';
        $to      = $this->getRecipients($input, 'to');
        $cc      = $this->getRecipients($input, 'cc');
        $bcc     = $this->getRecipients($input, 'bcc');
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
                $plainText
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

    /**
     * Extract and validate addresses from recipient repeater rows.
     *
     * @return array<string>|null
     */
    private function getRecipients(array $input, string $type): ?array {
        $rows = $input[$type . '_recipients'] ?? [];
        if (!is_array($rows)) {
            return null;
        }

        $recipients = [];
        foreach ($rows as $row) {
            $address = is_array($row) ? ($row['recipient_email_address'] ?? null) : null;
            if (!is_string($address) || strpbrk($address, "\r\n") !== false || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                return null;
            }
            $recipients[] = $address;
        }

        return $recipients;
    }
}