<?php

namespace MM\Meros\Services\Security;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use MM\Meros\Contracts\Security\MfaIdentity;
use PragmaRX\Google2FA\Google2FA;

final class Mfa {
    /**
     * Configures shared MFA storage, encryption, TOTP verification and limits.
     * Challenge lifetime and attempt-window durations are expressed in seconds.
     */
    public function __construct(
        private ConnectionInterface $database,
        private Encrypter $encrypter,
        private Google2FA $totp,
        private int $challengeLifetime,
        private int $attemptLimit,
        private int $attemptWindow,
    ) {}

    /**
     * Checks for a confirmed authenticator, excluding incomplete enrollment.
     */
    public function enrolled(MfaIdentity $identity): bool {
        return $this->credentials($identity)->whereNotNull('confirmed_at')->exists();
    }

    /**
     * Issues a browser-bound challenge after the adapter validates primary credentials.
     * The browser secret must be random; context must not contain passwords or factor secrets.
     *
     * @return string The raw challenge token, intended for a secure HttpOnly cookie.
     */
    public function issueChallenge(MfaIdentity $identity, string $browser, array $context): string {
        $this->credentials($identity)->insertOrIgnore([
            'identity_type' => $identity->mfaType(),
            'identity_id' => $identity->mfaId(),
        ]);
        $token = bin2hex(random_bytes(32));
        $this->database->table('meros_mfa_challenges')->where('expires_at', '<=', time())->delete();
        $this->database->table('meros_mfa_challenges')->insert([
            'token_hash' => hash('sha256', $token),
            'browser_hash' => hash('sha256', $browser),
            'identity_type' => $identity->mfaType(),
            'identity_id' => $identity->mfaId(),
            'stage' => $this->enrolled($identity) ? 'challenge' : 'enroll',
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'expires_at' => time() + $this->challengeLifetime,
        ]);
        return $token;
    }

    /**
     * Resolves an unexpired, browser-bound challenge and decodes its adapter-owned context.
     */
    public function challenge(string $token, string $browser): object {
        $challenge = $this->challengeQuery($token, $browser)->first();
        if ($challenge === null) {
            throw new MfaException('Your MFA login has expired or is invalid. Please sign in again.');
        }
        $challenge->context = json_decode($challenge->context, true, flags: JSON_THROW_ON_ERROR);
        return $challenge;
    }

    /**
     * Prepares or reuses a pending secret without replacing an active authenticator.
     * Provisioning data is sensitive and must only be displayed to the bound browser.
     *
     * @return array{secret: string, uri: string}
     */
    public function enrollment(MfaIdentity $identity, string $token, string $browser, string $issuer): array {
        return $this->database->transaction(function () use ($identity, $token, $browser, $issuer) {
            $credential = $this->lockedCredential($identity);
            $challenge = $this->challengeFor($identity, $token, $browser);
            if (!$this->canEnroll($credential, $challenge)) {
                throw new MfaException('MFA is already enrolled. Please sign in again.');
            }
            if ($credential->pending_secret === null) {
                $secret = $this->totp->generateSecretKey();
                $this->credentials($identity)->update(['pending_secret' => $this->encrypter->encrypt($secret, false)]);
            } else {
                $secret = $this->decrypt($credential->pending_secret);
            }
            return ['secret' => $secret, 'uri' => $this->totp->getQRCodeUrl($issuer, $identity->mfaLabel(), $secret)];
        });
    }

    /**
     * Activates a pending authenticator after proof of possession and revokes other challenges.
     *
     * @return list<string> New plaintext recovery codes for a single display.
     */
    public function confirmEnrollment(MfaIdentity $identity, string $token, string $browser, #[\SensitiveParameter] string $code): array {
        $result = $this->attempt($identity, $token, $browser, function (object $credential, object $challenge) use ($identity, $token, $code) {
            if (!$this->canEnroll($credential, $challenge) || $credential->pending_secret === null) {
                throw new MfaException('Enrollment is no longer available. Please sign in again.');
            }
            $step = $this->matchStep($credential->pending_secret, $code, null);
            if ($step === false) {
                return false;
            }
            $codes = $this->newRecoveryCodes();
            $this->credentials($identity)->update([
                'secret' => $credential->pending_secret,
                'pending_secret' => null,
                'confirmed_at' => time(),
                'last_step' => $step,
                'recovery_codes' => $this->hashRecoveryCodes($codes),
            ]);
            $this->invalidateOtherChallenges($identity, $token);
            return $codes;
        });
        if ($result === false) {
            throw new MfaException('The authenticator code is invalid or has already been used.');
        }
        return $result;
    }

    /**
     * Atomically consumes a TOTP timestep or recovery code and marks the challenge verified.
     * The adapter remains responsible for completing authentication.
     */
    public function verify(MfaIdentity $identity, string $token, string $browser, #[\SensitiveParameter] string $code, bool $recovery): void {
        $valid = $this->attempt($identity, $token, $browser, function (object $credential, object $challenge) use ($identity, $code, $recovery) {
            if ($challenge->stage !== 'challenge' || $credential->confirmed_at === null) {
                throw new MfaException('This MFA challenge is no longer available. Please sign in again.');
            }
            if ($recovery) {
                $hashes = json_decode($credential->recovery_codes, true, flags: JSON_THROW_ON_ERROR);
                $normalized = strtolower(str_replace(['-', ' '], '', $code));
                $index = array_search(hash('sha256', $normalized), $hashes, true);
                if ($index === false || !preg_match('/^[a-f0-9]{32}$/D', $normalized)) {
                    return false;
                }
                unset($hashes[$index]);
                $this->credentials($identity)->update(['recovery_codes' => json_encode(array_values($hashes), JSON_THROW_ON_ERROR)]);
            } else {
                $step = $this->matchStep($credential->secret, $code, $credential->last_step);
                if ($step === false) {
                    return false;
                }
                $this->credentials($identity)->update(['last_step' => $step]);
            }
            return true;
        });
        if ($valid === false) {
            throw new MfaException('The MFA code is invalid or has already been used.');
        }
    }

    /**
     * Replaces all recovery codes after verification and invalidates other pending challenges.
     *
     * @return list<string> New plaintext recovery codes for a single display.
     */
    public function regenerateRecoveryCodes(MfaIdentity $identity, string $token, string $browser): array {
        return $this->database->transaction(function () use ($identity, $token, $browser) {
            $this->lockedCredential($identity);
            if ($this->challengeFor($identity, $token, $browser)->stage !== 'verified') {
                throw new MfaException('Verify MFA before managing recovery codes.');
            }
            $codes = $this->newRecoveryCodes();
            $this->credentials($identity)->update(['recovery_codes' => $this->hashRecoveryCodes($codes)]);
            $this->invalidateOtherChallenges($identity, $token);
            return $codes;
        });
    }

    /**
     * Starts replacement from a verified challenge while retaining the current authenticator.
     */
    public function replaceAuthenticator(MfaIdentity $identity, string $token, string $browser): void {
        $this->database->transaction(function () use ($identity, $token, $browser) {
            $credential = $this->lockedCredential($identity);
            if ($credential->confirmed_at === null || $this->challengeFor($identity, $token, $browser)->stage !== 'verified') {
                throw new MfaException('Verify MFA before replacing your authenticator.');
            }
            $this->credentials($identity)->update(['pending_secret' => null]);
            $this->challengeQuery($token, $browser)->update(['stage' => 'replace']);
        });
    }

    /**
     * Discards pending replacement data and returns the challenge to verified management.
     */
    public function cancelReplacement(MfaIdentity $identity, string $token, string $browser): void {
        $this->database->transaction(function () use ($identity, $token, $browser) {
            $this->lockedCredential($identity);
            if ($this->challengeFor($identity, $token, $browser)->stage !== 'replace') {
                throw new MfaException('There is no authenticator replacement to cancel.');
            }
            $this->credentials($identity)->update(['pending_secret' => null]);
            $this->challengeQuery($token, $browser)->update(['stage' => 'verified']);
        });
    }

    /**
     * Consumes a verified challenge exactly once before the adapter establishes a session.
     *
     * @return array The primary-authentication context supplied by the adapter.
     */
    public function consumeChallenge(MfaIdentity $identity, string $token, string $browser): array {
        return $this->database->transaction(function () use ($identity, $token, $browser) {
            $this->lockedCredential($identity);
            $challenge = $this->challengeFor($identity, $token, $browser);
            if ($challenge->stage !== 'verified') {
                throw new MfaException('MFA verification is required before signing in.');
            }
            if ($this->challengeQuery($token, $browser)->delete() !== 1) {
                throw new MfaException('This MFA login has already been completed.');
            }
            return $challenge->context;
        });
    }

    /**
     * Removes factors only after verified reauthentication; the adapter must permit opting out.
     *
     * @return array The context needed to complete the authenticated management login.
     */
    public function disable(MfaIdentity $identity, string $token, string $browser): array {
        return $this->database->transaction(function () use ($identity, $token, $browser) {
            $this->lockedCredential($identity);
            $challenge = $this->challengeFor($identity, $token, $browser);
            if ($challenge->stage !== 'verified') {
                throw new MfaException('Verify MFA before disabling your authenticator.');
            }
            $this->forget($identity);
            return $challenge->context;
        });
    }

    /**
     * Removes an identity's factors and challenges for trusted administrative recovery.
     * The caller must authorize this operation; adapter policy and existing sessions are unchanged.
     */
    public function reset(MfaIdentity $identity): void {
        $this->database->transaction(function () use ($identity) {
            $this->credentials($identity)->lockForUpdate()->first();
            $this->forget($identity);
        });
    }

    /**
     * Deletes factor and challenge rows while the caller holds the identity's transaction lock.
     */
    private function forget(MfaIdentity $identity): void {
        $this->database->table('meros_mfa_challenges')
            ->where('identity_type', $identity->mfaType())->where('identity_id', $identity->mfaId())->delete();
        $this->credentials($identity)->delete();
    }

    /**
     * Serializes verification with identity-wide throttling and challenge-state updates.
     * Verifiers return false for invalid codes so failed-attempt updates are committed.
     */
    private function attempt(MfaIdentity $identity, string $token, string $browser, Closure $verify): mixed {
        return $this->database->transaction(function () use ($identity, $token, $browser, $verify) {
            $credential = $this->lockedCredential($identity);
            $challenge = $this->challengeFor($identity, $token, $browser);
            $window = (int) $credential->attempt_window;
            $attempts = time() - $window < $this->attemptWindow ? (int) $credential->failed_attempts : 0;
            if ($attempts >= $this->attemptLimit) {
                throw new MfaException('Too many MFA attempts. Please wait a few minutes before trying again.');
            }
            $result = $verify($credential, $challenge);
            // Return failures so the transaction commits the account-wide attempt counter.
            $this->credentials($identity)->update([
                'failed_attempts' => $result === false ? $attempts + 1 : 0,
                'attempt_window' => $attempts === 0 ? time() : $window,
            ]);
            if ($result !== false) {
                $this->challengeQuery($token, $browser)->update(['stage' => 'verified']);
            }
            return $result;
        });
    }

    /**
     * Matches a six-digit code only against TOTP timesteps newer than the last accepted step.
     */
    private function matchStep(string $encrypted, string $code, ?int $lastStep): int|false {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            return false;
        }
        return $this->totp->verifyKeyNewer($this->decrypt($encrypted), $code, $lastStep ?? 0, 1);
    }

    /**
     * Allows initial enrollment or an explicitly verified replacement flow.
     */
    private function canEnroll(object $credential, object $challenge): bool {
        return ($challenge->stage === 'enroll' && $credential->confirmed_at === null) ||
            ($challenge->stage === 'replace' && $credential->confirmed_at !== null);
    }

    /**
     * Decrypts a stored secret and rejects unexpected stored-value types.
     */
    private function decrypt(string $value): string {
        $secret = $this->encrypter->decrypt($value, false);
        if (!is_string($secret)) {
            throw new \LogicException('The stored MFA secret is not a string.');
        }
        return $secret;
    }

    /**
     * Locks the identity row inside the caller's transaction to serialize factor mutations.
     */
    private function lockedCredential(MfaIdentity $identity): object {
        $credential = $this->credentials($identity)->lockForUpdate()->first();
        if ($credential === null) {
            throw new MfaException('MFA credentials are unavailable. Please sign in again.');
        }
        return $credential;
    }

    /**
     * Validates adapter identifiers and scopes credential queries to their identity namespace.
     */
    private function credentials(MfaIdentity $identity): Builder {
        foreach ([$identity->mfaType(), $identity->mfaId()] as $identifier) {
            if (!preg_match('/^[a-z0-9][a-z0-9._:-]{0,99}$/D', $identifier)) {
                throw new \InvalidArgumentException('MFA identity namespaces and IDs must be lowercase ASCII identifiers of at most 100 characters.');
            }
        }
        return $this->database->table('meros_mfa_credentials')
            ->where('identity_type', $identity->mfaType())->where('identity_id', $identity->mfaId());
    }

    /**
     * Scopes challenge queries by hashed bearer token, browser binding and expiry.
     */
    private function challengeQuery(string $token, string $browser): Builder {
        return $this->database->table('meros_mfa_challenges')
            ->where('token_hash', hash('sha256', $token))
            ->where('browser_hash', hash('sha256', $browser))
            ->where('expires_at', '>', time());
    }

    /**
     * Additionally verifies that a browser-bound challenge belongs to the expected identity.
     */
    private function challengeFor(MfaIdentity $identity, string $token, string $browser): object {
        $challenge = $this->challenge($token, $browser);
        if ($challenge->identity_type !== $identity->mfaType() || $challenge->identity_id !== $identity->mfaId()) {
            throw new MfaException('This MFA challenge belongs to a different account.');
        }
        return $challenge;
    }

    /**
     * Revokes other pending flows after credentials or recovery codes change.
     */
    private function invalidateOtherChallenges(MfaIdentity $identity, string $token): void {
        $this->database->table('meros_mfa_challenges')
            ->where('identity_type', $identity->mfaType())->where('identity_id', $identity->mfaId())
            ->where('token_hash', '!=', hash('sha256', $token))->delete();
    }

    /**
     * Generates human-readable recovery codes with 128 bits of randomness each.
     *
     * @return list<string>
     */
    private function newRecoveryCodes(): array {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = implode('-', str_split(bin2hex(random_bytes(16)), 4));
        }
        return $codes;
    }

    /**
     * Encodes hashes of high-entropy recovery codes without their presentation hyphens.
     */
    private function hashRecoveryCodes(array $codes): string {
        return json_encode(array_map(fn (string $code) => hash('sha256', str_replace('-', '', $code)), $codes), JSON_THROW_ON_ERROR);
    }
}
