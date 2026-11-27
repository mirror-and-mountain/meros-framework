<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Support\Facades\Log;
use MM\Meros\App\BaseTheme;
use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Data\DataItem;
use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;
use MM\Meros\Contracts\Features\Storable;
use MM\Meros\Facades\Admin\Settings;
use MM\Meros\Facades\Data\Integrations;
use MM\Meros\Foundations\Testing\TestCase;
use MM\Meros\Foundations\Testing\WordPressState;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Psr\Log\LoggerInterface;

class AdminMailRoutingTest extends TestCase {
    private array $atts;
    private LoggerInterface $logger;

    protected function setUp(): void {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $logManager = $this->getMockBuilder(\stdClass::class)->addMethods(['channel'])->getMock();
        $logManager->method('channel')->with('meros-mail-logs')->willReturn($this->logger);
        Log::swap($logManager);
        WordPressState::$options['admin_email'] = 'admin@example.com';
        $this->atts = [
            'to' => 'admin@example.com',
            'subject' => 'Subject',
            'message' => 'Body',
            'headers' => [],
            'attachments' => [],
        ];
    }

    public function testAdminRoutingPreservesRecipientsAndHtmlHeaders(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturn(true);
        $mailer->expects(self::exactly(2))->method('sendMail')->with(
            ['ADMIN@example.com', 'other@example.com'], 'Subject', 'Body',
            ['cc@example.com', 'second@example.com'], ['bcc@example.com'], true
        )->willReturn(true);
        $this->configure($mailer);

        foreach ([
            [
                'to' => 'ADMIN@example.com, other@example.com',
                'headers' => "Cc: cc@example.com, second@example.com\r\nBcc: bcc@example.com\r\nContent-Type: text/html; charset=UTF-8",
            ],
            [
                'to' => ['ADMIN@example.com', 'other@example.com'],
                'headers' => ['Cc: cc@example.com', 'Cc: second@example.com', 'Bcc: bcc@example.com', 'Content-Type: text/html'],
            ],
        ] as $overrides) {
            $atts = array_replace($this->atts, $overrides);
            self::assertTrue(apply_filters('pre_wp_mail', null, $atts));
        }
    }

    public function testFailedSendLeavesTheOriginalMessageForWordPress(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturn(true);
        $mailer->expects(self::once())->method('sendMail')->with(
            ['admin@example.com'], 'Subject', 'Body', [], [], false
        )->willReturn(false);
        $this->configure($mailer);

        $original = $this->atts;
        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
        self::assertSame($original, $this->atts);
    }

    public function testThrownSendFailureIsLoggedAndFallsBack(): void {
        $exception = new RuntimeException('Transport unavailable');
        $this->logger->expects(self::once())->method('error')->with(
            'Admin mailer send failed; falling back to WordPress.', ['exception' => $exception]
        );
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturn(true);
        $mailer->expects(self::once())->method('sendMail')->willThrowException($exception);
        $this->configure($mailer);

        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
    }

    public function testUnrelatedAndUnsupportedMessagesStayWithWordPress(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturn(true);
        $mailer->expects(self::never())->method('sendMail');
        $this->configure($mailer);

        foreach ([
            ['to' => ['user@example.com']],
            ['to' => 'Administrator <admin@example.com>'],
            ['to' => "admin@example.com\r\nBcc: injected@example.com"],
            ['attachments' => ['/tmp/report.pdf']],
            ['embeds' => ['logo' => '/tmp/logo.png']],
            ['headers' => ['Reply-To: reply@example.com']],
            ['headers' => ['From: sender@example.com']],
            ['headers' => ['Content-Type: multipart/alternative; boundary=example']],
            ['headers' => ['Content-Type: text/plain; charset=ISO-8859-1']],
            ['headers' => ['Cc: not-an-address']],
            ['headers' => ["Cc: cc@example.com\r\nBcc: injected@example.com"]],
        ] as $overrides) {
            self::assertNull(apply_filters('pre_wp_mail', null, array_replace($this->atts, $overrides)));
        }

        self::assertTrue(apply_filters('pre_wp_mail', true, $this->atts));
        self::assertFalse(apply_filters('pre_wp_mail', false, $this->atts));
    }

    public function testUnavailableMailerLeavesWordPressInControl(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturnOnConsecutiveCalls(true, false);
        $mailer->expects(self::never())->method('sendMail');
        $this->configure($mailer);

        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
    }

    public function testDefaultOrUnsetSelectionDoesNotResolveAnIntegration(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::never())->method('ready');
        foreach ([null, '', 'default'] as $selection) {
            $this->configure($mailer, $selection);
            self::assertArrayNotHasKey('pre_wp_mail', WordPressState::$hooks);
        }
    }

    public function testAdminAndTransactionalMessagesUseTheirOwnMailers(): void {
        $adminMailer = $this->createMock(Mailer::class);
        $adminMailer->method('ready')->willReturn(true);
        $adminMailer->expects(self::once())->method('sendMail')->with(
            ['admin@example.com'], 'Subject', 'Body', [], [], false
        )->willReturn(true);
        $transactionalMailer = $this->createMock(Mailer::class);
        $transactionalMailer->method('ready')->willReturn(true);
        $transactionalMailer->expects(self::once())->method('sendMail')->with(
            ['user@example.com'], 'Subject', 'Body', ['cc@example.com'], ['bcc@example.com'], true
        )->willReturn(true);
        $this->configure($adminMailer, 'preferred', $transactionalMailer);

        self::assertTrue(apply_filters('pre_wp_mail', null, $this->atts));
        $atts = array_replace($this->atts, [
            'to' => 'user@example.com',
            'headers' => ['Cc: cc@example.com', 'Bcc: bcc@example.com', 'Content-Type: text/html'],
        ]);
        self::assertTrue(apply_filters('pre_wp_mail', null, $atts));
    }

    public function testTransactionalMailerWorksWhenAdminMailerIsDefault(): void {
        $adminMailer = $this->createMock(Mailer::class);
        $adminMailer->expects(self::never())->method('ready');
        $transactionalMailer = $this->createMock(Mailer::class);
        $transactionalMailer->method('ready')->willReturn(true);
        $transactionalMailer->expects(self::once())->method('sendMail')->with(
            ['user@example.com'], 'Subject', 'Body', [], [], false
        )->willReturn(true);
        $this->configure($adminMailer, 'default', $transactionalMailer);

        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
        $this->atts['to'] = ['user@example.com'];
        self::assertTrue(apply_filters('pre_wp_mail', null, $this->atts));
    }

    public function testFailedTransactionalSendFallsBackWithoutTryingAdminMailer(): void {
        $adminMailer = $this->createMock(Mailer::class);
        $adminMailer->method('ready')->willReturn(true);
        $adminMailer->expects(self::never())->method('sendMail');
        $transactionalMailer = $this->createMock(Mailer::class);
        $transactionalMailer->method('ready')->willReturn(true);
        $transactionalMailer->expects(self::once())->method('sendMail')->willReturn(false);
        $this->configure($adminMailer, 'preferred', $transactionalMailer);
        $this->atts['to'] = 'user@example.com';

        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
    }

    public function testPhpMailerSuccessIsLoggedWhenBothMailersAreDefault(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::never())->method('ready');
        $this->configure($mailer, 'default');
        $this->logger->expects(self::once())->method('info')->with(
            'PHP mailer send succeeded:', ['to' => ['admin@example.com'], 'subject' => 'Subject']
        );

        do_action('wp_mail_succeeded', array_replace($this->atts, ['to' => ['admin@example.com']]));
    }

    public function testFallbackIsLoggedAsSuccessOnlyAfterWordPressSends(): void {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('ready')->willReturn(true);
        $mailer->expects(self::once())->method('sendMail')->willReturn(false);
        $this->configure($mailer);
        $this->logger->expects(self::once())->method('error')->with(
            'Admin mailer send failed; falling back to WordPress.'
        );
        $this->logger->expects(self::once())->method('info')->with(
            'PHP mailer send succeeded:', ['to' => ['admin@example.com'], 'subject' => 'Subject']
        );

        self::assertNull(apply_filters('pre_wp_mail', null, $this->atts));
        do_action('wp_mail_failed', new RuntimeException('WordPress send failed'));
        do_action('wp_mail_succeeded', array_replace($this->atts, ['to' => ['admin@example.com']]));
    }

    private function configure(Mailer $mailer, ?string $selection = 'preferred', ?Mailer $transactionalMailer = null): void {
        $container = $this->createMock(Storable::class);
        $container->method('getItemValue')->willReturnMap([
            ['default_admin_mailer', false, $selection],
            ['default_transactional_mailer', false, $transactionalMailer === null ? 'default' : 'transactional'],
        ]);
        $settings = [];
        foreach (['default_admin_mailer', 'default_transactional_mailer'] as $name) {
            $setting = (new ReflectionClass(Setting::class))->newInstanceWithoutConstructor();
            (new ReflectionProperty(DataItem::class, 'container'))->setValue($setting, $container);
            (new ReflectionProperty(DataItem::class, 'name'))->setValue($setting, $name);
            $settings[$name] = $setting;
        }
        Settings::swap(new class($settings) {
            public function __construct(private array $settings) {}
            public function checkout(mixed $provider): self { return $this; }
            public function get(string $name): Setting { return $this->settings[$name]; }
        });
        Integrations::swap(new class(['preferred' => $mailer, 'transactional' => $transactionalMailer]) {
            public function __construct(private array $mailers) {}
            public function getMailer(string $name): Mailer { return $this->mailers[$name]; }
        });
        $theme = (new ReflectionClass(AdminMailRoutingTestTheme::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(BaseTheme::class, 'configureMailTransport'))->invoke($theme);
    }
}

class AdminMailRoutingTestTheme extends BaseTheme {}
