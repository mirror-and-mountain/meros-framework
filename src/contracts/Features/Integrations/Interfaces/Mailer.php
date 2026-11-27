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
     * @return boolean True if the email was sent successfully, false otherwise.
     */
    public function sendMail(
        array $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $html = false
    ): bool;

    /**
     * Gets the name of the mailer in the specified format.
     *
     * @param string $format
     *
     * @return string
     */
    public function getName(string $format = 'default'): string;

    /**
     * Gets the label of the mailer.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Checks whether the mailer is connected and enabled.
     *
     * @param string|null $capability
     *
     * @return boolean
     */
    public function ready(?string $capability = null): bool;

    /**
     * Indicates whether the mailer prefers HTML emails.
     *
     * @return boolean
     */
    public function prefersHTML(): bool;
}