<?php

use Illuminate\Database\SQLiteConnection;
use Illuminate\Encryption\Encrypter;
use MM\Meros\Contracts\Features\Data\TableCreator;
use MM\Meros\Contracts\Security\MfaIdentity;
use MM\Meros\Services\Security\Mfa;
use MM\Meros\Services\Security\MfaException;
use MM\Meros\Services\Security\WordPressIdentity;
use MM\Meros\Services\Security\WordPressMfa;
use PragmaRX\Google2FA\Google2FA;

function check(bool $condition, string $message): void {
    \PHPUnit\Framework\Assert::assertTrue($condition, $message);
}

function rejects(callable $action, string $message): void {
    try {
        $action();
    } catch (MfaException $exception) {
        check(str_contains($exception->getMessage(), $message), $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected an MFA rejection: ' . $message);
}

class TestMfaClock extends Google2FA {
    public int $step = 60000000;
    public function getTimestamp(): int { return $this->step; }
}

class TestMfaIdentity implements MfaIdentity {
    public function __construct(private string $type, private string $id) {}
    public function mfaType(): string { return $this->type; }
    public function mfaId(): string { return $this->id; }
    public function mfaLabel(): string { return 'MFA Test'; }
}

class WP_User {
    public string $user_login = 'mfa-test';
    public string $user_pass = 'password-hash';
    public string $user_activation_key = '';
    public function __construct(public int $ID, public array $roles) {}
}
class WP_Error {
    public function __construct(public string $code = '', public string $message = '') {}
}
class TestMfaRedirect extends RuntimeException {}
class TestMfaRendered extends RuntimeException {}
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function is_ssl(): bool { return true; }
function wp_unslash(string $value): string { return stripslashes($value); }
function wp_salt(string $scheme): string { return 'mfa-test-key'; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function wp_login_url(string $redirect = '', bool $reauth = false): string {
    $args = array_filter(['redirect_to' => $redirect, 'reauth' => $reauth ? '1' : '']);
    return 'https://example.test/wp-login.php' . ($args ? '?' . http_build_query($args) : '');
}
function add_query_arg(string $name, string $value, string $url): string { return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([$name => $value]); }
function wp_validate_redirect(string $url, string $fallback): string { return str_starts_with($url, 'https://example.test/') ? $url : $fallback; }
function wp_safe_redirect(string $url): void { throw new TestMfaRedirect($url); }
function wp_die(string $message, string $title = '', array $args = []): never { throw new RuntimeException($message); }
function get_userdata(int $id): WP_User|false { return $GLOBALS['users'][$id] ?? false; }
function get_current_user_id(): int { return $GLOBALS['currentUser'] ?? 0; }
function wp_set_current_user(int $id): void { $GLOBALS['currentUser'] = $id; }
function wp_set_auth_cookie(int $id, bool $remember): void { $GLOBALS['authCookies'][] = [$id, $remember]; }
function do_action(string $name, mixed ...$args): void { $GLOBALS['actions'][] = $name; }
function apply_filters(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function nocache_headers(): void {}
function login_header(string $title, string $message, WP_Error $error): void { echo '<h1>' . esc_html($title) . '</h1><p>' . esc_html($error->message) . '</p>'; }
function login_footer(): void { throw new TestMfaRendered(); }

function mfaMarkup(string $html): DOMXPath {
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    return new DOMXPath($document);
}


function startWordPressMfa(WordPressMfa $login, bool $manage = false, int $userId = 10): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'pwd' => 'primary-password',
        'meros_mfa_csrf' => hash_hmac('sha256', 'login', $_COOKIE['__Host-meros_mfa_browser_mfa_tests']),
        'meros_mfa_manage' => $manage ? '1' : '',
        'redirect_to' => $manage ? admin_url('profile.php') : '',
    ];
    $login->prepareLogin();
    try { $login->authenticate($GLOBALS['users'][$userId]); } catch (TestMfaRedirect) {}
    $token = $_COOKIE['__Host-meros_mfa_pending_mfa_tests'];
    $browser = $_COOKIE['__Host-meros_mfa_browser_mfa_tests'];
    $_POST = ['meros_mfa_csrf' => hash_hmac('sha256', $token, $browser)];
    return [$token, $browser];
}

