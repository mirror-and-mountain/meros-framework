<?php

namespace MM\Meros\Services\Security;

use MM\Meros\Contracts\Security\MfaIdentity;

final class WordPressIdentity implements MfaIdentity {
    public function __construct(private \WP_User $user) {}

    public function mfaType(): string {
        return 'wordpress';
    }

    public function mfaId(): string {
        return (string) $this->user->ID;
    }

    public function mfaLabel(): string {
        return $this->user->user_login;
    }
}
