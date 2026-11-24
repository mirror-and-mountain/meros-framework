<?php

use Illuminate\Database\Schema\Blueprint;
use MM\Meros\Contracts\Features\Data\TableCreator;

return new class extends TableCreator {
    protected function configure(): void {
        $this->description('Encrypted MFA credentials and single-use recovery code hashes.');
        $this->define(function (Blueprint $table) {
            $table->id();
            $table->string('identity_type', 100);
            $table->string('identity_id', 100);
            $table->unique(['identity_type', 'identity_id']);
            $table->text('secret')->nullable();
            $table->text('pending_secret')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->unsignedBigInteger('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_step')->nullable();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->unsignedBigInteger('attempt_window')->default(0);
        });
    }
};
