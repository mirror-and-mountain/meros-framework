# Multi-factor authentication

Meros supports authenticator-app TOTP and single-use recovery codes on the native WordPress login screen. Enrollment, verification, recovery-code regeneration and authenticator replacement all run through `wp-login.php`; no WordPress page is required.

## Setup

All MFA settings, service bindings, login hooks and policy configuration live in [the Auth orchestrator](../src/app/Admin/Auth/Orchestrator.php). Settings retain their existing names and storage in the framework settings container.

1. Install Composer dependencies in the consuming theme.
2. Keep The application encryption key (`APP_KEY`) stable and backed up. Losing that key makes existing authenticator secrets unreadable. Use HTTPS in production.
3. Install the two optional framework tables using Meros's table tooling or WP-CLI:

   ```sh
   wp meros:install framework --table-name=meros_mfa_credentials
   wp meros:install framework --table-name=meros_mfa_challenges
   ```

4. Enable MFA under **Settings > Security > Multi-Factor Authentication**.
5. Select **Required MFA Roles** to require enrollment for those roles, or leave the selection empty for optional enrollment only. Every user can enable MFA from their own profile while the master switch is enabled. Keep an existing administrator session open while testing enrollment and save the generated recovery codes.

The credentials table stores encrypted secrets and individual recovery-code hashes. The challenges table stores hashed, short-lived, browser-bound login tokens. Tables use the configured database connection and its table prefix; they are not created during login requests.

## Enforcement and login

- The master switch enables the WordPress MFA feature. Selected **Required MFA Roles** must enroll; every other user can opt in through their own profile.
- An empty role selection makes enrollment optional for everyone. Enrolled users must still verify MFA on interactive login, even if their role is not selected or a previous role requirement is removed.
- Disabling the master switch suspends WordPress MFA enforcement and hides its profile controls without deleting enrolled factors.
- Existing WordPress sessions remain valid. Enforcement begins on the next interactive login.
- After successful primary authentication, required or enrolled users must complete MFA before a new WordPress authentication cookie is issued.
- Enrollment is confirmed with a valid authenticator code. Recovery codes are then shown once, before completing sign-in.
- WordPress's normal remember-me setting, login event and safe login redirects are preserved.

**This protects interactive WordPress login only. XML-RPC, application passwords, REST authentication and other non-interactive flows remain unchanged. Third-party login forms/SSO flows that do not use the native login action are outside this initial integration.**

Challenges expire after ten minutes. Five invalid factor attempts within five minutes block further factor verification until that attempt window expires. Limits are per identity, not per challenge, so restarting login does not reset them. Accepted TOTP timesteps cannot be reused, and recovery-code consumption is transactional.

## Managing and recovering MFA

Every user can choose **Enable MFA** under **Users > Profile > Multi-Factor Authentication**, regardless of role. The shortcut asks for the user's password before enrollment and returns to their profile after completion. Once enrolled, the profile shows **Manage MFA** instead.

Choose **Manage MFA** on the login screen or profile and authenticate with your password and current authenticator or a recovery code. This allows you to regenerate recovery codes or replace the authenticator. Old recovery codes become invalid when regenerated. During replacement, the existing authenticator remains active until the new one is confirmed; replacement can also be cancelled.

MFA screens use a wider, responsive login panel with grouped actions. Authenticator inputs expose `autocomplete="one-time-code"`, six-digit numeric input hints and username context for password managers. Recovery codes have their own expandable form so they do not dilute the authenticator field's autofill hints. AutoFill suggestions still depend on the browser, operating system and a matching verification code saved for the website/account in the password manager; iCloud Passwords suggestions cannot be guaranteed by HTML alone.

Users whose roles do not require MFA can also choose **Disable MFA** after password-and-factor reauthentication. This removes their factors, recovery codes and pending challenges. MFA required by role policy cannot be disabled by an individual user, including when a role becomes required during management.

If both the authenticator and recovery codes are lost, a trusted server administrator can reset a specific WordPress identity using WP-CLI (replace `123` with the verified user's ID):

```sh
wp eval '$user = get_userdata(123); if (!$user) { WP_CLI::error("Unknown user."); } app(\MM\Meros\Services\Security\Mfa::class)->reset(new \MM\Meros\Services\Security\WordPressIdentity($user));'
```

This removes that identity's factors and pending challenges, not its role requirement or existing WordPress sessions. The next login requires fresh enrollment if role policy requires MFA; otherwise the user can opt in again from their profile. The reset operation must only be exposed through an appropriately authorized administrative integration; it is not an unauthenticated recovery endpoint.

With the master switch enabled, interactive logins must check enrollment even for optional roles. Missing tables or an unusable encryption key therefore fail closed for interactive logins rather than bypassing an unknown MFA enrollment. Existing sessions remain available, and profile pages show an explicit status error if MFA storage is unavailable.

## Package adapters

The shared [MFA service](../src/services/Security/Mfa.php) does not depend on WordPress users, cookies, views or Laravel authentication guards. Packages implement [MfaIdentity](../src/contracts/Security/MfaIdentity.php) for their own user model:

- `mfaType()`: a stable, package-specific namespace, for example `meros_crm.portal`.
- `mfaId()`: the stable user ID as a string, independent of the WordPress user ID space.
- `mfaLabel()`: the account label displayed in authenticator provisioning.

Namespaces and IDs must be lowercase ASCII identifiers of at most 100 characters, using letters, digits, dots, underscores, colons or hyphens, beginning with a letter or digit.

Resolve the shared service through `app(Mfa::class)`. A future portal adapter should:

1. Validate primary credentials without establishing an authenticated portal session.
2. Issue a challenge with `issueChallenge()`, binding a cryptographically random browser secret to a secure HttpOnly cookie and protecting submissions against CSRF.
3. Use `enrollment()` / `confirmEnrollment()` for enrollment, or `verify()` for an existing factor.
4. Atomically consume the verified challenge with `consumeChallenge()` before logging in through the explicit portal guard and regenerating the session.

The package owns its enforcement policy, primary-credential freshness checks, safe redirects, browser binding, CSRF protection and UI. It must enforce required-role rules itself before exposing `disable()`, which requires a verified challenge and returns the context for completing login. Identity objects must be resolved from trusted authentication state, never taken directly from a submitted user ID. Secret keys, codes and challenge tokens must not be logged or sent to external QR services.

The shared service is registered even when WordPress MFA is disabled, allowing packages to apply their own policy. No CRM models, portal routes or portal views are modified by this implementation.
