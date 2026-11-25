<?php

namespace {
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post(string $body): string {
            return $body;
        }
    }

    if (!function_exists('wp_mail')) {
        function wp_mail(array $to, string $subject, string $body, array $headers): bool {
            $GLOBALS['emailTestCalls'][] = compact('to', 'subject', 'body', 'headers');
            return $GLOBALS['emailTestResult'];
        }
    }
}

namespace MM\Meros\Tests\Unit {
    use Illuminate\Support\Facades\Facade;
    use MM\Meros\App\Components\FormActions\SendAnEmail;
    use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;
    use MM\Meros\Facades\Data\Integrations;
    use MM\Meros\Support\EmailHelpers;
    use PHPUnit\Framework\TestCase;
    use ReflectionClass;

    /**
     * Tests email action recipients, headers, validation and delivery results with WordPress and integration doubles.
     *
     * @runTestsInSeparateProcesses
     * @preserveGlobalState disabled
     */
    class SendAnEmailTest extends TestCase {
        private SendAnEmail $action;
        private array $input;

        protected function setUp(): void {
            $this->action = (new ReflectionClass(SendAnEmail::class))->newInstanceWithoutConstructor();
            $GLOBALS['emailTestCalls'] = [];
            $GLOBALS['emailTestResult'] = true;
            $this->input = [
                'email_subject' => 'Subject',
                'email_body' => '<p>Hello</p>',
                'to_recipients' => [
                    ['recipient_email_address' => 'to@example.com'],
                    ['recipient_email_address' => 'another@example.com'],
                ],
                'cc_recipients' => [['recipient_email_address' => 'cc@example.com']],
                'bcc_recipients' => [['recipient_email_address' => 'bcc@example.com']],
            ];
        }

        protected function tearDown(): void {
            Facade::clearResolvedInstances();
        }

        public function testWordPressReceivesExtractedRecipientsAndHeaders(): void {
            $response = $this->action->run($this->input);

            self::assertFalse($response->hasError());
            self::assertSame('Email sent successfully.', $response->output());
            self::assertSame([[
                'to' => ['to@example.com', 'another@example.com'],
                'subject' => 'Subject',
                'body' => '<p style="margin:0;">Hello</p>',
                'headers' => [
                    'Content-Type: text/html; charset=UTF-8',
                    'Cc: cc@example.com',
                    'Bcc: bcc@example.com',
                ],
            ]], $GLOBALS['emailTestCalls']);
        }

        public function testWordPressFailureIsReported(): void {
            $GLOBALS['emailTestResult'] = false;
            unset($this->input['cc_recipients'], $this->input['bcc_recipients']);
            $response = $this->action->run($this->input);

            self::assertTrue($response->hasError());
            self::assertSame('Failed to send email using WordPress.', $response->getError());
            self::assertSame(['Content-Type: text/html; charset=UTF-8'], $GLOBALS['emailTestCalls'][0]['headers']);
        }

        public function testInvalidRecipientsAndMissingToDoNotSend(): void {
            foreach ([
                ['cc_recipients' => [['recipient_email_address' => "cc@example.com\r\nBcc: attacker@example.com"]]],
                ['bcc_recipients' => [['recipient_email_address' => 'not-an-email']]],
                ['cc_recipients' => [['missing' => 'cc@example.com']]],
                ['bcc_recipients' => 'invalid'],
                ['to_recipients' => []],
            ] as $invalid) {
                self::assertTrue($this->action->run(array_replace($this->input, $invalid))->hasError());
            }
            self::assertSame([], $GLOBALS['emailTestCalls']);
        }

        public function testIntegrationReceivesAddressesAndReportsSuccessOrFailure(): void {
            $mailer = new EmailTestMailer();
            Integrations::swap(new class($mailer) {
                public function __construct(private EmailTestMailer $mailer) {}
                public function getMailer(string $name): Mailer { return $this->mailer; }
            });
            $this->input['send_using'] = 'test_mailer';
            $response = $this->action->run($this->input);

            self::assertFalse($response->hasError());
            self::assertSame('Email sent successfully.', $response->output());
            self::assertSame([
                ['to@example.com', 'another@example.com'], 'Subject', '<p style="margin:0;">Hello</p>',
                ['cc@example.com'], ['bcc@example.com'], true,
            ], $mailer->calls[0]);
            $mailer->result = false;
            self::assertTrue($this->action->run($this->input)->hasError());
            self::assertSame([], $GLOBALS['emailTestCalls'], 'A failed integration send must not be retried through WordPress.');
        }

        public function testUnavailableIntegrationFallsBackToWordPress(): void {
            $mailer = new EmailTestMailer();
            $mailer->available = false;
            Integrations::swap(new class($mailer) {
                public function __construct(private EmailTestMailer $mailer) {}
                public function getMailer(string $name): Mailer { return $this->mailer; }
            });
            $this->input['send_using'] = 'test_mailer';
            self::assertFalse($this->action->run($this->input)->hasError());
            self::assertSame([], $mailer->calls);
            self::assertCount(1, $GLOBALS['emailTestCalls']);
        }

        public function testHtmlEmailPreservesEditorLinesWithoutParagraphMargins(): void {
            $this->input['email_body'] = '<p>Hello, Toby,</p><p><br></p>'
                . '<p style="color:red">Thanks for your <strong>message</strong>.</p><p><br></p><p>Admin</p>';
            $this->action->run($this->input);

            self::assertSame(
                '<p style="margin:0;">Hello, Toby,</p><p style="margin:0;"><br></p>'
                . '<p style="color:red;margin:0;">Thanks for your <strong>message</strong>.</p>'
                . '<p style="margin:0;"><br></p><p style="margin:0;">Admin</p>',
                $GLOBALS['emailTestCalls'][0]['body']
            );
        }

        public function testPlainTextEmailConvertsMarkupToNewlinesForBothMailers(): void {
            $this->input['email_body'] = "<p>H\u{00e9}llo &amp; welcome</p><p><br></p>"
                . '<p>Thanks<br>Admin</p>';
            $this->input['send_as_plain_text'] = true;
            $this->action->run($this->input);
            self::assertSame("H\u{00e9}llo & welcome\n\nThanks\nAdmin", $GLOBALS['emailTestCalls'][0]['body']);
            self::assertSame('Content-Type: text/plain; charset=UTF-8', $GLOBALS['emailTestCalls'][0]['headers'][0]);

            $mailer = new EmailTestMailer();
            Integrations::swap(new class($mailer) {
                public function __construct(private EmailTestMailer $mailer) {}
                public function getMailer(string $name): Mailer { return $this->mailer; }
            });
            $this->input['send_using'] = 'test_mailer';
            $this->action->run($this->input);
            self::assertSame("H\u{00e9}llo & welcome\n\nThanks\nAdmin", $mailer->calls[0][2]);
            self::assertFalse($mailer->calls[0][5]);
        }

        public function testSharedHelpersAreAvailableIndependentlyOfTheAction(): void {
            self::assertSame(['cc@example.com'], EmailHelpers::getRecipients($this->input, 'cc'));
            self::assertSame([], EmailHelpers::getRecipients([], 'bcc'));
            self::assertNull(EmailHelpers::getRecipients(['to_recipients' => [['recipient_email_address' => 'bad']]], 'to'));
            self::assertSame('<p style="margin:0;">Hello</p>', EmailHelpers::formatBody('<p>Hello</p>', false));
            self::assertSame("Hello\nWorld", EmailHelpers::formatBody('<p>Hello</p><p>World</p>', true));
        }
    }

    class EmailTestMailer implements Mailer {
        public bool $available = true;
        public bool $result = true;
        public array $calls = [];

        public function getName(string $format = 'default'): string { return 'test_mailer'; }
        public function getLabel(): string { return 'Test Mailer'; }
        public function ready(?string $capability = null): bool { return $this->available; }
        public function sendMail(array $to, string $subject, string $body, array $cc = [], array $bcc = [], bool $html = false): bool {
            $this->calls[] = [$to, $subject, $body, $cc, $bcc, $html];
            return $this->result;
        }
    }
}
