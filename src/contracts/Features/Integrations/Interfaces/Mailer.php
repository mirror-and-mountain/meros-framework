<?php

namespace MM\Meros\Contracts\Features\Integrations\Interfaces;

interface Mailer {
    /**
     * Sends an email with the specified parameters. Recipient arrays contain email address strings.
     *
     * @param array<string> $to
     * @param string  $subject
     * @param string  $body
     * @param array<string> $cc
     * @param array<string> $bcc
     * @param boolean $html
     *
     * @return boolean
     */
    public function sendMail(
        array $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $html = false
    ): bool;

    public function getName(string $format = 'default'): string;
    public function getLabel(): string;
    public function ready(?string $capability = null): bool;
}