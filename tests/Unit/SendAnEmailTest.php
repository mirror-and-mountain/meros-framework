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
            self::assertSame(['success' => true], $response->output());
            self::assertSame([[
                'to' => ['to@example.com', 'another@example.com'],
                'subject' => 'Subject',
                'body' => '<p>Hello</p>',
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
            self::assertSame(['success' => true], $response->output());
            self::assertSame([
                ['to@example.com', 'another@example.com'], 'Subject', '<p>Hello</p>',
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
