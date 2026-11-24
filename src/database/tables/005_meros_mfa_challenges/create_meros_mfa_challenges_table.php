<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {
    protected function configure(): void {
        $this->description('Short-lived, browser-bound MFA login challenges.');
        $this->dependsOn('meros_mfa_credentials');
        $this->define(function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->char('browser_hash', 64);
            $table->string('identity_type', 100);
            $table->string('identity_id', 100);
            $table->index(['identity_type', 'identity_id']);
            $table->string('stage', 20);
            $table->text('context');
            $table->unsignedBigInteger('expires_at')->index();
        });
    }
};
