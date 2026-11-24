<?php

namespace MM\Meros\Contracts\Security;

interface MfaIdentity {
    public function mfaType(): string;
    public function mfaId(): string;
    public function mfaLabel(): string;
}
