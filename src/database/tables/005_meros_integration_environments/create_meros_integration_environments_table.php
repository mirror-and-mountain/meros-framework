<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        SchemaManager::create('meros_integration_environments', $installer, function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('integration_handle')->index();
            $table->string('environment')->index();
            $table->string('label');
            $table->boolean('is_default')->default(false)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'integration_handle', 'environment'],
                'meros_integration_environments_unique'
            );
        });

        if (SchemaManager::hasTable('meros_integration_accounts')) {
            SchemaManager::table('meros_integration_accounts', $installer, function (Blueprint $table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('meros_integration_accounts', 'environment')) {
                    $table->string('environment')->default('production')->after('auth_type')->index();
                }

                // Allow the same account label to be reused across different environments.
                try {
                    $table->dropUnique('meros_integration_accounts_unique');
                } catch (\Throwable $exception) {
                    // Index may not exist in some installs; ignore.
                }

                try {
                    $table->unique(
                        ['provider', 'integration_handle', 'environment', 'label'],
                        'meros_integration_accounts_unique'
                    );
                } catch (\Throwable $exception) {
                    // Unique may already be present; ignore.
                }
            });
        }
    }

    public function down(string $installer): void {
        if (SchemaManager::hasTable('meros_integration_accounts')) {
            SchemaManager::table('meros_integration_accounts', $installer, function (Blueprint $table) {
                try {
                    $table->dropUnique('meros_integration_accounts_unique');
                } catch (\Throwable $exception) {
                    // Index may not exist; ignore.
                }

                try {
                    $table->unique(
                        ['provider', 'integration_handle', 'label'],
                        'meros_integration_accounts_unique'
                    );
                } catch (\Throwable $exception) {
                    // Unique may already be present; ignore.
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('meros_integration_accounts', 'environment')) {
                    $table->dropColumn('environment');
                }
            });
        }

        SchemaManager::dropIfExists('meros_integration_environments', $installer);
    }
};
