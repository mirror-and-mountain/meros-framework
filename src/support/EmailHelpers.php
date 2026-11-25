<?php

namespace MM\Meros\Support;

use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\Contracts\Features\Automation\FlowAction;
use MM\Meros\Contracts\Features\Components\Field;

class EmailHelpers {
    /**
     * Add To, CC and BCC recipient repeater inputs to a flow action.
     */
    public static function addRecipientInputs(FlowAction $action): void {
        foreach (['to', 'cc', 'bcc'] as $type) {
            $action->addInput(
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

    /**
     * Formats an email body for HTML or plain-text output. Sanitize untrusted HTML before calling.
     */
    public static function formatBody(string $body, bool $plainText): string {
        $document = new \DOMDocument('1.0', 'UTF-8');
        if (!$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $body . '</body></html>', LIBXML_NONET)) {
            throw new \UnexpectedValueException('Unable to parse the email body.');
        }
        $root = $document->getElementsByTagName('body')->item(0);
        if ($root === null) {
            throw new \UnexpectedValueException('Unable to locate the email body.');
        }

        if ($plainText) {
            return rtrim(self::bodyText($root), "\n");
        }

        // Quill uses paragraphs for individual lines; email clients otherwise add paragraph margins.
        foreach ($document->getElementsByTagName('p') as $paragraph) {
            $style = rtrim($paragraph->getAttribute('style'), "; \t\n\r");
            $paragraph->setAttribute('style', ($style === '' ? '' : $style . ';') . 'margin:0;');
        }
        $html = '';
        foreach ($root->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }
        return $html;
    }

    private static function bodyText(\DOMNode $node): string {
        if ($node instanceof \DOMText) return $node->data;
        if ($node->nodeName === 'br') return "\n";

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::bodyText($child);
        }
        if (in_array($node->nodeName, ['p', 'div', 'li', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) &&
            !str_ends_with($text, "\n")) {
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Extract and validate addresses from recipient repeater rows.
     * $type selects the input key (e.g. "to" reads "to_recipients").
     * Each row must contain a "recipient_email_address" string.
     *
     * @return array<string>|null Null if any row is invalid; an absent group returns an empty array.
     */
    public static function getRecipients(array $input, string $type): ?array {
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
