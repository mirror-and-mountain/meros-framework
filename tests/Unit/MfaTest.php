<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Encryption\Encrypter;
use MM\Meros\Contracts\Features\Data\TableCreator;
use MM\Meros\Contracts\Security\MfaIdentity;
use MM\Meros\Services\Security\Mfa;
use MM\Meros\Services\Security\MfaException;
use MM\Meros\Services\Security\WordPressIdentity;
use MM\Meros\Services\Security\WordPressMfa;
use PragmaRX\Google2FA\Google2FA;
use LogicException;
use PDO;
use ReflectionProperty;
use TestMfaClock;
use TestMfaIdentity;
use TestMfaRedirect;
use TestMfaRendered;
use WP_Error;
use WP_User;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MfaTest extends TestCase {
    protected function setUp(): void {
        require_once dirname(__DIR__) . '/Support/MfaTestFixtures.php';
    }

    public function testCredentialEnrollmentRecoveryReplacementAndReset(): void {
        $database = new SQLiteConnection(new PDO('sqlite::memory:'));
        foreach (['004_meros_mfa_credentials', '005_meros_mfa_challenges'] as $directory) {
            $name = substr($directory, 4);
            $creator = require dirname(dirname(__DIR__)) . '/src/database/tables/' . $directory . '/create_' . $name . '_table.php';
            $creator->__init();
            $definition = (new ReflectionProperty(TableCreator::class, 'definition'))->getValue($creator);
            $database->getSchemaBuilder()->create($name, $definition->callback);
        }
        $clock = new TestMfaClock();
        $encrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $mfa = new Mfa($database, $encrypter, $clock, 600, 5, 300);
        $browser = bin2hex(random_bytes(32));
        $identity = new TestMfaIdentity('wordpress', '1');
        $portal = new TestMfaIdentity('meros_crm.portal', '1');
        $token = $mfa->issueChallenge($identity, $browser, []);
        $setup = $mfa->enrollment($identity, $token, $browser, 'Meros Test');
        check(str_starts_with($setup['uri'], 'otpauth://totp/'), 'Enrollment must generate a standard authenticator URI.');
        $stored = $database->table('meros_mfa_credentials')->first();
        check($stored->pending_secret !== $setup['secret'] && !$mfa->enrolled($identity), 'Unconfirmed secrets must be encrypted and not enable MFA.');
        rejects(fn () => $mfa->challenge($token, 'another browser'), 'expired');
        rejects(fn () => $mfa->enrollment($portal, $token, $browser, 'Meros Test'), 'credentials');
        rejects(fn () => $mfa->consumeChallenge($identity, $token, $browser), 'verification is required');
        rejects(fn () => $mfa->confirmEnrollment($identity, $token, $browser, 'invalid'), 'invalid');
        $codes = $mfa->confirmEnrollment($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']));
        check(count($codes) === 8 && count(array_unique($codes)) === 8, 'Confirmation must issue distinct recovery codes.');
        check($mfa->enrolled($identity) && !$mfa->enrolled($portal), 'Identical IDs in different identity namespaces must be isolated.');
        $stored = $database->table('meros_mfa_credentials')->first();
        check(!str_contains($stored->recovery_codes, str_replace('-', '', $codes[0])), 'Recovery codes must be stored as hashes.');
        $mfa->consumeChallenge($identity, $token, $browser);
        rejects(fn () => $mfa->consumeChallenge($identity, $token, $browser), 'expired');
        
        $token = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->verify($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']), false), 'already been used');
        $clock->step++;
        $mfa->verify($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']), false);
        $mfa->consumeChallenge($identity, $token, $browser);
        $token = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->verify($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']), false), 'already been used');
        $mfa->verify($identity, $token, $browser, $codes[0], true);
        $newCodes = $mfa->regenerateRecoveryCodes($identity, $token, $browser);
        $mfa->consumeChallenge($identity, $token, $browser);
        $token = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->verify($identity, $token, $browser, $codes[0], true), 'invalid');
        rejects(fn () => $mfa->verify($identity, $token, $browser, $codes[1], true), 'invalid');
        $mfa->verify($identity, $token, $browser, $newCodes[0], true);
        $mfa->consumeChallenge($identity, $token, $browser);
        $token = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->verify($identity, $token, $browser, $newCodes[0], true), 'invalid');
        
        $database->table('meros_mfa_credentials')->update(['failed_attempts' => 0]);
        for ($i = 0; $i < 5; $i++) {
            $token = $mfa->issueChallenge($identity, $browser, []);
            rejects(fn () => $mfa->verify($identity, $token, $browser, 'invalid', false), 'invalid');
        }
        $clock->step++;
        rejects(fn () => $mfa->verify($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']), false), 'Too many');
        $database->table('meros_mfa_credentials')->update(['attempt_window' => time() - 301]);
        $mfa->verify($identity, $token, $browser, $clock->getCurrentOtp($setup['secret']), false);
        $database->table('meros_mfa_challenges')->update(['expires_at' => time() - 1]);
        rejects(fn () => $mfa->consumeChallenge($identity, $token, $browser), 'expired');
        
        $token = $mfa->issueChallenge($identity, $browser, []);
        $mfa->verify($identity, $token, $browser, $newCodes[1], true);
        $mfa->replaceAuthenticator($identity, $token, $browser);
        $replacement = $mfa->enrollment($identity, $token, $browser, 'Meros Test');
        $stored = $database->table('meros_mfa_credentials')->first();
        check($encrypter->decrypt($stored->secret, false) === $setup['secret'], 'Starting a replacement must leave the existing authenticator active.');
        $mfa->cancelReplacement($identity, $token, $browser);
        check($mfa->challenge($token, $browser)->stage === 'verified', 'A replacement must be cancellable without locking the user out.');
        $mfa->replaceAuthenticator($identity, $token, $browser);
        $replacement = $mfa->enrollment($identity, $token, $browser, 'Meros Test');
        $otherToken = $mfa->issueChallenge($identity, $browser, []);
        $replacementCodes = $mfa->confirmEnrollment($identity, $token, $browser, $clock->getCurrentOtp($replacement['secret']));
        $stored = $database->table('meros_mfa_credentials')->first();
        check($encrypter->decrypt($stored->secret, false) === $replacement['secret'], 'Confirming a replacement must activate the new authenticator.');
        rejects(fn () => $mfa->challenge($otherToken, $browser), 'expired');
        $mfa->consumeChallenge($identity, $token, $browser);
        $token = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->verify($identity, $token, $browser, $newCodes[2], true), 'invalid');
        $mfa->verify($identity, $token, $browser, $replacementCodes[0], true);
        
        $portalToken = $mfa->issueChallenge($portal, $browser, []);
        $portalSetup = $mfa->enrollment($portal, $portalToken, $browser, 'Portal Test');
        $mfa->confirmEnrollment($portal, $portalToken, $browser, $clock->getCurrentOtp($portalSetup['secret']));
        $mfa->reset($identity);
        check(!$mfa->enrolled($identity) && $mfa->enrolled($portal), 'An administrative reset must clear only the selected identity.');
        rejects(fn () => $mfa->challenge($token, $browser), 'expired');
        
        $optionalToken = $mfa->issueChallenge($identity, $browser, ['flow' => 'optional']);
        $optionalSetup = $mfa->enrollment($identity, $optionalToken, $browser, 'Meros Test');
        rejects(fn () => $mfa->disable($identity, $optionalToken, $browser), 'Verify MFA before disabling');
        $mfa->confirmEnrollment($identity, $optionalToken, $browser, $clock->getCurrentOtp($optionalSetup['secret']));
        $otherToken = $mfa->issueChallenge($identity, $browser, []);
        rejects(fn () => $mfa->disable($identity, $optionalToken, 'another browser'), 'expired');
        check($mfa->disable($identity, $optionalToken, $browser) === ['flow' => 'optional'], 'Disabling must return the verified primary-authentication context.');
        check(!$mfa->enrolled($identity) && $mfa->enrolled($portal), 'Self-service disabling must remove only the authenticated identity factors.');
        rejects(fn () => $mfa->challenge($optionalToken, $browser), 'expired');
        rejects(fn () => $mfa->challenge($otherToken, $browser), 'expired');
    }

    public function testWordPressLoginEnrollmentAndManagement(): void {
        ob_start();
        $database = new SQLiteConnection(new PDO('sqlite::memory:'));
        foreach (['004_meros_mfa_credentials', '005_meros_mfa_challenges'] as $directory) {
            $name = substr($directory, 4);
            $creator = require dirname(dirname(__DIR__)) . '/src/database/tables/' . $directory . '/create_' . $name . '_table.php';
            $creator->__init();
            $definition = (new ReflectionProperty(TableCreator::class, 'definition'))->getValue($creator);
            $database->getSchemaBuilder()->create($name, $definition->callback);
        }
        $clock = new TestMfaClock();
        $encrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $mfa = new Mfa($database, $encrypter, $clock, 600, 5, 300);
        $browser = bin2hex(random_bytes(32));
        $identity = new TestMfaIdentity('wordpress', '1');
        $portal = new TestMfaIdentity('meros_crm.portal', '1');
        define('COOKIEHASH', 'mfa_tests');
        $GLOBALS['users'][10] = new WP_User(10, ['administrator']);
        $GLOBALS['users'][11] = new WP_User(11, ['subscriber']);
        $GLOBALS['requiredRoles'] = ['administrator'];
        $GLOBALS['authCookies'] = [];
        $login = new WordPressMfa(
            fn () => $mfa,
            fn (WP_User $user) => array_intersect($GLOBALS['requiredRoles'], $user->roles) !== [],
            'Meros Test',
            600,
        );
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['pwd' => 'primary-password', 'rememberme' => 'forever', 'redirect_to' => 'https://attacker.test/'];
        $_REQUEST = [];
        $login->initialize();
        $login->prepareLogin();
        check($login->authenticate($GLOBALS['users'][11]) === $GLOBALS['users'][11], 'Unenrolled users outside the required roles must sign in without MFA.');
        $GLOBALS['requiredRoles'] = [];
        check($login->authenticate($GLOBALS['users'][10]) === $GLOBALS['users'][10], 'An empty role selection must not require MFA.');
        $GLOBALS['requiredRoles'] = ['administrator'];
        check($login->authenticate($GLOBALS['users'][10]) instanceof WP_Error, 'A protected login must reject invalid browser CSRF proof.');
        $_POST['meros_mfa_csrf'] = hash_hmac('sha256', 'login', $_COOKIE['__Host-meros_mfa_browser_mfa_tests']);
        $error = new WP_Error('wrong_password', 'Incorrect password');
        check($login->authenticate($error) === $error, 'MFA must never override failed primary authentication.');
        try {
            $staleUser = clone $GLOBALS['users'][10];
            $GLOBALS['users'][10]->user_pass = 'rehashed-during-primary-authentication';
            $login->authenticate($staleUser);
        } catch (TestMfaRedirect $redirect) {
            check(str_contains($redirect->getMessage(), 'action=meros_mfa'), 'A successful primary login must route to the native MFA action.');
        }
        check($GLOBALS['authCookies'] === [], 'Primary authentication must not issue a WordPress authentication cookie.');
        $wpIdentity = new WordPressIdentity($GLOBALS['users'][10]);
        $wpToken = $_COOKIE['__Host-meros_mfa_pending_mfa_tests'];
        $wpBrowser = $_COOKIE['__Host-meros_mfa_browser_mfa_tests'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        $html = ob_get_contents();
        check(str_contains($html, '<svg') && str_contains($html, 'Manual setup key'), 'Enrollment must render a local QR code in the login screen.');
        check(str_contains($html, 'https://example.test/wp-login.php?action=meros_mfa'), 'MFA forms must stay on the WordPress login endpoint.');
        $markup = mfaMarkup($html);
        check($markup->query('//input[@id="meros-mfa-code"][@type="text"][@autocomplete="one-time-code"][@inputmode="numeric"][@maxlength="6"][@pattern="[0-9]{6}"]')->length === 1, 'Enrollment must advertise a dedicated six-digit OTP field to autofill providers.');
        check($markup->query('//form/input[@autocomplete="username"][@value="mfa-test"]')->length === 1, 'The OTP form must include the verified account context for password managers.');
        $wpSetup = $mfa->enrollment($wpIdentity, $wpToken, $wpBrowser, 'Meros Test');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['operation' => 'enroll', 'code' => $clock->getCurrentOtp($wpSetup['secret']), 'meros_mfa_csrf' => hash_hmac('sha256', $wpToken, $wpBrowser)];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        $html = ob_get_contents();
        check($GLOBALS['authCookies'] === [] && substr_count($html, '<li><code>') === 8, 'Enrollment confirmation must show recovery codes before completing login.');
        preg_match_all('~<li><code>([^<]+)</code></li>~', $html, $matches);
        $wpCodes = $matches[1];
        $_POST['operation'] = 'complete';
        try {
            $login->handle();
        } catch (TestMfaRedirect $redirect) {
            check($redirect->getMessage() === admin_url(), 'External login redirects must fall back to WordPress admin.');
        }
        check($GLOBALS['authCookies'] === [[10, true]], 'Only completed MFA may issue the normal remembered WordPress session.');
        check(in_array('wp_login', $GLOBALS['actions'], true), 'Completed MFA must fire the native WordPress login event.');
        
        [$wpToken, $wpBrowser] = startWordPressMfa($login);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        $markup = mfaMarkup(ob_get_contents());
        check($markup->query('//form[.//input[@autocomplete="one-time-code"]][not(.//input[@name="recovery"])]')->length === 1, 'Authenticator entry must be isolated from recovery-code submission.');
        check($markup->query('//details/form[.//input[@name="recovery"][@value="1"]]//input[@name="code"][@autocomplete="off"][not(@pattern)]')->length === 1, 'Recovery codes must retain a separate non-numeric input and explicit recovery mode.');
        check($markup->query('//form//form')->length === 0, 'Alternative verification methods must not produce nested forms.');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST += ['operation' => 'complete'];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 1, 'Submitting completion without verifying MFA must never authenticate the user.');
        $_POST['operation'] = 'verify';
        $_POST['code'] = 'invalid';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 1 && str_contains(ob_get_contents(), 'invalid'), 'Invalid MFA codes must leave the login unauthenticated.');
        $clock->step++;
        $_POST['code'] = $clock->getCurrentOtp($wpSetup['secret']);
        $_POST['meros_mfa_csrf'] = 'invalid';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 1 && $mfa->challenge($wpToken, $wpBrowser)->stage === 'challenge', 'Failed CSRF checks must not verify a valid code.');
        $_POST['meros_mfa_csrf'] = hash_hmac('sha256', $wpToken, $wpBrowser);
        try { $login->handle(); } catch (TestMfaRedirect) {}
        check(count($GLOBALS['authCookies']) === 2, 'An enrolled user must complete normal login after a valid second factor.');
        
        startWordPressMfa($login);
        $GLOBALS['users'][10]->user_pass = 'changed-after-password-verification';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 2 && str_contains(ob_get_contents(), 'account or MFA policy has changed'), 'A password change must invalidate an unfinished login.');
        
        startWordPressMfa($login);
        $GLOBALS['requiredRoles'] = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 2 && str_contains(ob_get_contents(), 'id="meros-mfa-code"'), 'Removing a role requirement must not bypass an enrolled user second factor.');
        [$wpToken, $wpBrowser] = startWordPressMfa($login);
        check($mfa->challenge($wpToken, $wpBrowser)->stage === 'challenge', 'An empty required-role selection must still challenge enrolled users at new logins.');
        $GLOBALS['requiredRoles'] = ['administrator'];
        
        [$wpToken, $wpBrowser] = startWordPressMfa($login, true);
        $_POST += ['operation' => 'verify', 'recovery' => '1', 'code' => $wpCodes[0]];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 2 && str_contains(ob_get_contents(), 'Replace authenticator'), 'Management must require factor verification and must not authenticate early.');
        $_POST['operation'] = 'replace';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(str_contains(ob_get_contents(), '<svg') && str_contains(ob_get_contents(), 'existing authenticator remains active'), 'Replacement must run in the native login UI without prematurely discarding the old authenticator.');
        $markup = mfaMarkup(ob_get_contents());
        check($markup->evaluate('string((//form//button)[1])') === 'Confirm MFA', 'Pressing Enter during replacement must default to confirming the code, not cancelling replacement.');
        $_POST['operation'] = 'cancel-replacement';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->challenge($wpToken, $wpBrowser)->stage === 'verified', 'Cancelling replacement must return to verified management.');
        $_POST['operation'] = 'regenerate';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(substr_count(ob_get_contents(), '<li><code>') === 8, 'Reauthenticated management must display newly generated recovery codes.');
        check(!str_contains(ob_get_contents(), '>Disable MFA<'), 'Required roles must not be offered a disable action.');
        $_POST['operation'] = 'disable';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->enrolled($wpIdentity) && str_contains(ob_get_contents(), 'cannot be disabled'), 'A forged disable operation must not remove required MFA.');
        
        ob_clean();
        $login->profile($GLOBALS['users'][10]);
        $markup = mfaMarkup(ob_get_contents());
        $profileLink = $markup->evaluate('string(//a[@class="button"]/@href)');
        parse_str(parse_url($profileLink, PHP_URL_QUERY), $profileQuery);
        check(($profileQuery['reauth'] ?? '') === '1' && ($profileQuery['meros_mfa_manage'] ?? '') === '1', 'Profile management must enter the existing password-and-factor reauthentication flow.');
        check(($profileQuery['redirect_to'] ?? '') === admin_url('profile.php'), 'Profile management must return to the user profile after completion.');
        $GLOBALS['requiredRoles'] = [];
        ob_clean();
        $login->profile($GLOBALS['users'][10]);
        check(str_contains(ob_get_contents(), 'optional') && str_contains(ob_get_contents(), '>Manage MFA<'), 'Enrolled users outside required roles must retain profile management.');
        ob_clean();
        $login->profile($GLOBALS['users'][11]);
        check(ob_get_contents() === '', 'Profile management must never expose an action for another user.');
        
        $GLOBALS['currentUser'] = 11;
        $GLOBALS['requiredRoles'] = ['subscriber'];
        startWordPressMfa($login, false, 11);
        $GLOBALS['requiredRoles'] = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(count($GLOBALS['authCookies']) === 2 && str_contains(ob_get_contents(), 'account or MFA policy has changed'), 'Removing a requirement before enrollment must invalidate the forced-enrollment flow rather than silently opting the user in.');
        ob_clean();
        $login->profile($GLOBALS['users'][11]);
        check(str_contains(ob_get_contents(), '>Enable MFA<') && str_contains(ob_get_contents(), 'optional'), 'Unenrolled users of any role must be offered optional enrollment on their own profile.');
        [$optionalToken, $wpBrowser] = startWordPressMfa($login, true, 11);
        $optionalIdentity = new WordPressIdentity($GLOBALS['users'][11]);
        check($mfa->challenge($optionalToken, $wpBrowser)->stage === 'enroll', 'Optional enrollment must begin after successful primary authentication.');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(str_contains(ob_get_contents(), '<svg') && !str_contains(ob_get_contents(), 'Your role requires MFA'), 'Optional enrollment must render without claiming the user role requires it.');
        $optionalSetup = $mfa->enrollment($optionalIdentity, $optionalToken, $wpBrowser, 'Meros Test');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST += ['operation' => 'enroll', 'code' => $clock->getCurrentOtp($optionalSetup['secret'])];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        preg_match_all('~<li><code>([^<]+)</code></li>~', ob_get_contents(), $matches);
        $optionalCodes = $matches[1];
        check(count($optionalCodes) === 8 && count($GLOBALS['authCookies']) === 2, 'Optional enrollment must show recovery codes before establishing a session.');
        $_POST['operation'] = 'complete';
        try { $login->handle(); } catch (TestMfaRedirect $redirect) {
            check($redirect->getMessage() === admin_url('profile.php'), 'Optional enrollment must return to the profile.');
        }
        check(count($GLOBALS['authCookies']) === 3 && $mfa->enrolled($optionalIdentity), 'Completing optional enrollment must enable MFA and establish the user session.');
        
        [$optionalToken, $wpBrowser] = startWordPressMfa($login, false, 11);
        check($mfa->challenge($optionalToken, $wpBrowser)->stage === 'challenge' && count($GLOBALS['authCookies']) === 3, 'Opted-in users must verify MFA at normal login even with no required roles.');
        $_POST += ['operation' => 'disable'];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->enrolled($optionalIdentity) && str_contains(ob_get_contents(), 'Start a management login'), 'Optional MFA may only be disabled through the management flow.');
        $_POST['operation'] = 'verify';
        $_POST['recovery'] = '1';
        $_POST['code'] = $optionalCodes[0];
        try { $login->handle(); } catch (TestMfaRedirect) {}
        check(count($GLOBALS['authCookies']) === 4, 'Optional users must complete normal login after valid factor verification.');
        
        [$optionalToken, $wpBrowser] = startWordPressMfa($login, true, 11);
        $_POST += ['operation' => 'disable'];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->enrolled($optionalIdentity) && str_contains(ob_get_contents(), 'Verify MFA before disabling'), 'Management must verify an existing factor before optional MFA can be disabled.');
        $_POST['operation'] = 'verify';
        $_POST['recovery'] = '1';
        $_POST['code'] = $optionalCodes[1];
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check(str_contains(ob_get_contents(), '>Disable MFA<') && count($GLOBALS['authCookies']) === 4, 'Verified optional management must offer disabling without issuing an early session.');
        $GLOBALS['requiredRoles'] = ['subscriber'];
        $_POST['operation'] = 'disable';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->enrolled($optionalIdentity) && str_contains(ob_get_contents(), 'cannot be disabled') && !str_contains(ob_get_contents(), '>Disable MFA<'), 'A role requirement added during management must immediately prevent disabling.');
        $GLOBALS['requiredRoles'] = [];
        $_POST['meros_mfa_csrf'] = 'invalid';
        ob_clean();
        try { $login->handle(); } catch (TestMfaRendered) {}
        check($mfa->enrolled($optionalIdentity) && str_contains(ob_get_contents(), 'security check failed'), 'Disabling must reject invalid browser CSRF proof.');
        $_POST['meros_mfa_csrf'] = hash_hmac('sha256', $optionalToken, $wpBrowser);
        $otherToken = $mfa->issueChallenge($optionalIdentity, $wpBrowser, []);
        try { $login->handle(); } catch (TestMfaRedirect $redirect) {
            check($redirect->getMessage() === admin_url('profile.php'), 'Disabling optional MFA must return to the profile.');
        }
        check(!$mfa->enrolled($optionalIdentity) && count($GLOBALS['authCookies']) === 5, 'Verified optional disabling must remove factors and complete the authenticated session.');
        rejects(fn () => $mfa->challenge($otherToken, $wpBrowser), 'expired');
        $_POST = ['pwd' => 'primary-password'];
        check($login->authenticate($GLOBALS['users'][11]) === $GLOBALS['users'][11], 'Optional users who disable MFA must return to password-only login.');
        ob_clean();
        $login->profile($GLOBALS['users'][11]);
        check(str_contains(ob_get_contents(), '>Enable MFA<') && str_contains(ob_get_contents(), 'not set up'), 'The profile must offer re-enrollment after optional MFA is disabled.');
        
        $apiLogin = new WordPressMfa(fn () => throw new LogicException('Non-interactive login must not invoke MFA.'), fn () => true, 'Test', 600);
        check($apiLogin->authenticate($GLOBALS['users'][10]) === $GLOBALS['users'][10], 'Non-interactive authentication must remain unchanged.');
        ob_end_clean();
    }

}
