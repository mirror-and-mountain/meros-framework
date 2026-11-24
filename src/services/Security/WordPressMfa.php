<?php

namespace MM\Meros\Services\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;

final class WordPressMfa {
    private bool $interactive = false;

    /**
     * Accepts lazy MFA resolution and the role policy configured by the Auth orchestrator.
     */
    public function __construct(
        private Closure $service,
        private Closure $required,
        private string $issuer,
        private int $lifetime,
    ) {}

    /**
     * Establishes a browser-binding cookie without creating a WordPress authentication session.
     */
    public function initialize(): void {
        if ($this->cookieValue('browser') === '') {
            $this->cookie('browser', bin2hex(random_bytes(32)), time() + $this->lifetime);
        }
    }

    /**
     * Marks native login submissions as interactive without affecting API authentication.
     */
    public function prepareLogin(): void {
        $this->interactive = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Adds browser-bound CSRF proof and preserves management intent on the password form.
     */
    public function fields(): void {
        echo '<input type="hidden" name="meros_mfa_csrf" value="' . esc_attr($this->csrf('login')) . '">';
        if (($_REQUEST['meros_mfa_manage'] ?? null) === '1') {
            echo '<input type="hidden" name="meros_mfa_manage" value="1">';
        }
    }

    /**
     * Links the native login screen to password-and-factor reauthenticated MFA management.
     */
    public function navigation(): void {
        echo '<p style="text-align:center"><a href="' . esc_url($this->managementUrl()) . '">Manage MFA</a></p>';
    }

    /**
     * Widens only MFA login screens and groups actions without changing ordinary login styling.
     */
    public function styles(): void {
        if (($_REQUEST['action'] ?? null) !== 'meros_mfa') {
            return;
        }
        wp_add_inline_style('login', '
            .login-action-meros_mfa #login { width:460px; max-width:calc(100% - 40px); }
            .meros-mfa-form .button { float:none; max-width:100%; height:auto; white-space:normal; text-align:center; }
            .meros-mfa-form svg { max-width:100%; height:auto; }
            .meros-mfa-form code { overflow-wrap:anywhere; }
            .meros-mfa-form .meros-mfa-recovery-codes { margin:12px 0 16px; padding-inline-start:20px; list-style:disc; }
            .meros-mfa-form .meros-mfa-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; align-items:center; gap:12px; margin-top:20px; }
            .meros-mfa-actions .button-primary { order:1; }
            .meros-mfa-management { display:grid; gap:20px; margin-top:16px; }
            .meros-mfa-management p { margin-top:8px; }
            .meros-mfa-recovery { margin-top:20px; }
            .meros-mfa-recovery summary { cursor:pointer; }
        ');
    }

    /**
     * Offers self-service enrollment or management without allowing changes to another user's factors.
     */
    public function profile(\WP_User $user): void {
        if ($user->ID !== get_current_user_id()) {
            return;
        }
        echo '<h2>Multi-Factor Authentication</h2><table class="form-table" role="presentation"><tr>';
        echo '<th scope="row">Authenticator and recovery codes</th><td>';
        try {
            $enrolled = $this->mfa()->enrolled(new WordPressIdentity($user));
            echo '<p><strong>' . ($enrolled ? 'MFA is enabled.' : 'MFA is not set up.') . '</strong></p>';
            echo ($this->required)($user)
                ? '<p>Your role requires MFA. It cannot be disabled while that requirement applies.</p>'
                : '<p>MFA is optional for your role. You can enable it for additional account protection.</p>';
            echo '<p><a class="button" href="' . esc_url($this->managementUrl(admin_url('profile.php'))) . '">' . ($enrolled ? 'Manage MFA' : 'Enable MFA') . '</a></p>';
            echo '<p class="description">Set up MFA, regenerate recovery codes, or change your authenticator app. You will be asked to sign in again and verify your current factor before changing an existing setup.</p>';
        } catch (QueryException|DecryptException|MissingAppKeyException $exception) {
            $this->reportUnavailable($exception);
            echo '<p>MFA status is unavailable. Ask the site administrator to check the MFA tables and application encryption key.</p>';
        }
        echo '</td></tr></table>';
    }

    /**
     * Intercepts successful protected logins before WordPress issues authentication cookies.
     * Failed primary authentication and non-interactive flows pass through unchanged.
     */
    public function authenticate(mixed $user): mixed {
        if (!$this->interactive || !($user instanceof \WP_User)) {
            return $user;
        }
        // Password verification may have rehashed the password and invalidated the original user object.
        $user = get_userdata($user->ID);
        if (!($user instanceof \WP_User)) {
            return new \WP_Error('meros_mfa_user', 'The account is no longer available.');
        }
        try {
            $manage = $this->input('meros_mfa_manage') === '1';
            $identity = new WordPressIdentity($user);
            if (!($this->required)($user) && !$manage && !$this->mfa()->enrolled($identity)) {
                return $user;
            }
            if ($this->input('pwd') === '') {
                return new \WP_Error('meros_mfa_password', 'Enter your password to continue with MFA.');
            }
            if (!$this->validCsrf('login')) {
                return new \WP_Error('meros_mfa_csrf', 'Please reload the login screen and try again.');
            }
            $token = $this->mfa()->issueChallenge($identity, $this->cookieValue('browser'), [
                'password_stamp' => $this->passwordStamp($user),
                'remember' => !empty($_POST['rememberme']),
                'redirect' => wp_validate_redirect($this->input('redirect_to'), admin_url()),
                'manage' => $manage,
            ]);
            $this->cookie('browser', $this->cookieValue('browser'), time() + $this->lifetime);
            $this->cookie('pending', $token, time() + $this->lifetime);
            wp_safe_redirect($this->url());
            exit;
        } catch (QueryException|DecryptException|MissingAppKeyException $exception) {
            $this->unavailable($exception);
        }
    }

    /**
     * Validates pending login context and dispatches native enrollment, challenge and management forms.
     */
    public function handle(): void {
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        try {
            $token = $this->cookieValue('pending');
            $browser = $this->cookieValue('browser');
            $challenge = $this->mfa()->challenge($token, $browser);
            $user = get_userdata((int) $challenge->identity_id);
            if ($challenge->identity_type !== 'wordpress' || !($user instanceof \WP_User) ||
                !hash_equals($this->passwordStamp($user), $challenge->context['password_stamp'])
            ) {
                throw new MfaException('Your account or MFA policy has changed. Please sign in again.');
            }
            $identity = new WordPressIdentity($user);
            $required = ($this->required)($user);
            if (!$required && !$challenge->context['manage'] && !$this->mfa()->enrolled($identity)) {
                throw new MfaException('Your account or MFA policy has changed. Please sign in again.');
            }
            $codes = [];
            $error = '';
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                try {
                    if (!$this->validCsrf($token)) {
                        throw new MfaException('The security check failed. Please try again.');
                    }
                    switch ($this->input('operation')) {
                        case 'enroll':
                            $codes = $this->mfa()->confirmEnrollment($identity, $token, $browser, $this->input('code'));
                            break;
                        case 'verify':
                            $this->mfa()->verify($identity, $token, $browser, $this->input('code'), $this->input('recovery') === '1');
                            if (!$challenge->context['manage']) {
                                $this->finish($user, $identity, $token, $browser);
                            }
                            break;
                        case 'regenerate':
                            if (!$challenge->context['manage']) {
                                throw new MfaException('Start a management login before regenerating recovery codes.');
                            }
                            $codes = $this->mfa()->regenerateRecoveryCodes($identity, $token, $browser);
                            break;
                        case 'replace':
                            if (!$challenge->context['manage']) {
                                throw new MfaException('Start a management login before replacing your authenticator.');
                            }
                            $this->mfa()->replaceAuthenticator($identity, $token, $browser);
                            break;
                        case 'cancel-replacement':
                            $this->mfa()->cancelReplacement($identity, $token, $browser);
                            break;
                        case 'disable':
                            if (!$challenge->context['manage']) {
                                throw new MfaException('Start a management login before disabling MFA.');
                            }
                            if ($required) {
                                throw new MfaException('MFA cannot be disabled while your role requires it.');
                            }
                            $context = $this->mfa()->disable($identity, $token, $browser);
                            $this->completeLogin($user, $context);
                        case 'complete':
                            $this->finish($user, $identity, $token, $browser);
                        default:
                            throw new MfaException('Invalid MFA operation.');
                    }
                } catch (MfaException $exception) {
                    $error = $exception->getMessage();
                }
            }
            $challenge = $this->mfa()->challenge($token, $browser);
            $this->render($identity, $challenge, $token, $browser, $codes, $error, $required);
        } catch (MfaException $exception) {
            $this->cookie('pending', '', time() - 3600);
            login_header('Multi-Factor Authentication', '', new \WP_Error('meros_mfa', $exception->getMessage()));
            echo '<p><a href="' . esc_url(wp_login_url()) . '">Return to sign in</a></p>';
            login_footer();
        } catch (QueryException|DecryptException|MissingAppKeyException $exception) {
            $this->unavailable($exception);
        }
        exit;
    }

    /**
     * Consumes verified proof before issuing native cookies, firing login hooks and safely redirecting.
     */
    private function finish(\WP_User $user, WordPressIdentity $identity, string $token, string $browser): never {
        $context = $this->mfa()->consumeChallenge($identity, $token, $browser);
        $this->completeLogin($user, $context);
    }

    /**
     * Completes native login after factor verification or an authorized optional-MFA removal.
     */
    private function completeLogin(\WP_User $user, array $context): never {
        if ($user->user_activation_key !== '') {
            $updated = wp_update_user(['ID' => $user->ID, 'user_activation_key' => '']);
            if (is_wp_error($updated)) {
                wp_die('The account could not be updated. Please sign in again.');
            }
        }
        $this->cookie('pending', '', time() - 3600);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $context['remember']);
        do_action('wp_login', $user->user_login, $user);
        $redirect = apply_filters('login_redirect', $context['redirect'], $context['redirect'], $user);
        wp_safe_redirect(is_string($redirect) ? wp_validate_redirect($redirect, admin_url()) : admin_url());
        exit;
    }

    /**
     * Renders the current MFA stage in WordPress's login layout, keeping provisioning data local.
     */
    private function render(WordPressIdentity $identity, object $challenge, string $token, string $browser, array $codes, string $error, bool $required): void {
        $enrollment = in_array($challenge->stage, ['enroll', 'replace'], true)
            ? $this->mfa()->enrollment($identity, $token, $browser, $this->issuer)
            : null;
        login_header('Multi-Factor Authentication', '', new \WP_Error($error === '' ? '' : 'meros_mfa', $error));
        $this->formStart($identity, $token);
        if ($codes !== []) {
            echo '<p>Save these recovery codes somewhere safe. Each works once and they will not be shown again.</p><ul class="meros-mfa-recovery-codes">';
            foreach ($codes as $code) {
                echo '<li><code>' . esc_html($code) . '</code></li>';
            }
            echo '</ul>';
        }
        if ($enrollment !== null) {
            $writer = new Writer(new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd()));
            $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($enrollment['uri']));
            echo $challenge->stage === 'replace'
                ? '<p>Scan this QR code with your new authenticator. Your existing authenticator remains active until you confirm the replacement.</p>'
                : '<p>Scan this QR code with an authenticator app, then enter its six-digit code to enable MFA.</p>';
            echo '<div style="text-align:center">' . $svg . '</div>';
            echo '<p>Manual setup key: <code>' . esc_html($enrollment['secret']) . '</code></p>';
            echo '<input type="hidden" name="operation" value="enroll">';
            $this->codeField();
            echo '<div class="meros-mfa-actions">';
            echo '<button class="button button-primary button-large">Confirm MFA</button>';
            if ($challenge->stage === 'replace') {
                echo '<button class="button" type="submit" name="operation" value="cancel-replacement" formnovalidate>Cancel replacement</button>';
            }
            echo '</div>';
        } elseif ($challenge->stage === 'challenge') {
            echo '<p>Enter the six-digit code from your authenticator app.</p>';
            echo '<input type="hidden" name="operation" value="verify">';
            $this->codeField();
            echo '<div class="meros-mfa-actions"><button class="button button-primary button-large">Verify MFA</button></div>';
        } else {
            echo '<p>MFA verified.</p>';
            if ($challenge->context['manage']) {
                echo '<div class="meros-mfa-management"><div><button class="button" name="operation" value="regenerate">Generate new recovery codes</button>';
                echo '<p>Generating new codes invalidates all previous recovery codes.</p>';
                echo '</div><div><button class="button" name="operation" value="replace">Replace authenticator</button></div></div>';
                if (!$required) {
                    echo '<div class="meros-mfa-management"><div><button class="button" name="operation" value="disable">Disable MFA</button>';
                    echo '<p>Disabling MFA removes your authenticator and recovery codes.</p></div></div>';
                }
            }
            echo '<div class="meros-mfa-actions"><button class="button button-primary button-large" name="operation" value="complete">Continue to WordPress</button></div>';
        }
        echo '</form>';
        if ($challenge->stage === 'challenge') {
            echo '<details class="meros-mfa-recovery"><summary>Use a recovery code</summary>';
            $this->formStart($identity, $token);
            echo '<input type="hidden" name="operation" value="verify"><input type="hidden" name="recovery" value="1">';
            echo '<p><label for="meros-mfa-recovery-code">Recovery code</label>';
            echo '<input class="input" type="text" name="code" id="meros-mfa-recovery-code" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="64" required></p>';
            echo '<div class="meros-mfa-actions"><button class="button button-primary button-large">Verify recovery code</button></div></form></details>';
        }
        login_footer();
    }

    /**
     * Starts a CSRF-protected form with trusted username context for password-manager autofill.
     */
    private function formStart(WordPressIdentity $identity, string $token): void {
        echo '<form class="meros-mfa-form" method="post" action="' . esc_url($this->url()) . '">';
        echo '<input type="hidden" name="meros_mfa_csrf" value="' . esc_attr($this->csrf($token)) . '">';
        echo '<input type="hidden" name="username" autocomplete="username" value="' . esc_attr($identity->mfaLabel()) . '">';
    }

    /**
     * Renders a dedicated six-digit authenticator field, separate from recovery-code entry.
     */
    private function codeField(): void {
        echo '<p><label for="meros-mfa-code">Authentication code</label>';
        echo '<input class="input" type="text" name="code" id="meros-mfa-code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocapitalize="off" spellcheck="false" enterkeyhint="done" required autofocus></p>';
    }

    /**
     * Forces password reauthentication while preserving the requested management return location.
     */
    private function managementUrl(string $redirect = ''): string {
        return add_query_arg('meros_mfa_manage', '1', wp_login_url($redirect, true));
    }

    /**
     * Resolves the shared service only when MFA storage or encryption is actually needed.
     */
    private function mfa(): Mfa {
        return ($this->service)();
    }

    /**
     * Reads an unslashed scalar POST value without accepting submitted arrays as field values.
     */
    private function input(string $name): string {
        $value = $_POST[$name] ?? '';
        return is_string($value) ? wp_unslash($value) : '';
    }

    /**
     * Binds pending primary-authentication proof to the current password hash.
     */
    private function passwordStamp(\WP_User $user): string {
        return hash_hmac('sha256', $user->user_pass, wp_salt('auth'));
    }

    /**
     * Derives purpose-specific CSRF proof from the browser's HttpOnly binding secret.
     */
    private function csrf(string $purpose): string {
        return hash_hmac('sha256', $purpose, $this->cookieValue('browser'));
    }

    /**
     * Checks posted CSRF proof using a nonempty browser secret and timing-safe comparison.
     */
    private function validCsrf(string $purpose): bool {
        return $this->cookieValue('browser') !== '' && hash_equals($this->csrf($purpose), $this->input('meros_mfa_csrf'));
    }

    /**
     * Resolves the native login action used for every MFA stage.
     */
    private function url(): string {
        return add_query_arg('action', 'meros_mfa', wp_login_url());
    }

    /**
     * Uses site-specific cookie names and host-only protection when HTTPS is available.
     */
    private function cookieName(string $name): string {
        return (is_ssl() ? '__Host-' : '') . 'meros_mfa_' . $name . '_' . COOKIEHASH;
    }

    /**
     * Accepts only the expected 256-bit hexadecimal browser and challenge tokens.
     */
    private function cookieValue(string $name): string {
        $value = $_COOKIE[$this->cookieName($name)] ?? '';
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) ? $value : '';
    }

    /**
     * Sets or expires an HttpOnly binding cookie and fails explicitly if headers cannot be sent.
     */
    private function cookie(string $name, string $value, int $expires): void {
        if (!setcookie($this->cookieName($name), $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ])) {
            wp_die('The MFA security cookie could not be set. Please sign in again.');
        }
        $_COOKIE[$this->cookieName($name)] = $value;
    }

    /**
     * Logs infrastructure failure types without secret-bearing query text and fails protected login closed.
     */
    private function unavailable(\Throwable $exception): never {
        $this->reportUnavailable($exception);
        wp_die('MFA is unavailable. Ask the site administrator to check the MFA tables and application encryption key.', 'MFA unavailable', ['response' => 503]);
    }

    /**
     * Records infrastructure failure types without exposing credential-bearing exception messages.
     */
    private function reportUnavailable(\Throwable $exception): void {
        error_log('Meros MFA unavailable: ' . $exception::class . ' (code ' . $exception->getCode() . ').');
    }
}
