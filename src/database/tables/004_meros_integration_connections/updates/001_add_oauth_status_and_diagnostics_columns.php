<?php

namespace MM\Meros\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

return new class extends Migration {
    public function up(string $installer): void {
        if (!SchemaManager::hasTable('meros_integration_connections')) {
            return;
        }

        SchemaManager::table('meros_integration_connections', $installer, function (Blueprint $table) {
            if (!Schema::hasColumn('meros_integration_connections', 'status')) {
                $table->string('status')->default('inactive')->index()->after('is_active');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'status_reason')) {
                $table->string('status_reason')->nullable()->after('status');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'last_error')) {
                $table->text('last_error')->nullable()->after('status_reason');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'last_error_at')) {
                $table->timestamp('last_error_at')->nullable()->after('last_error');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'connected_at')) {
                $table->timestamp('connected_at')->nullable()->after('last_used_at');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('connected_at');
            }

            if (!Schema::hasColumn('meros_integration_connections', 'last_refreshed_at')) {
                $table->timestamp('last_refreshed_at')->nullable()->after('revoked_at');
            }
        });

        DB::table('meros_integration_connections')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere('status', 'inactive');
            })
            ->update([
                'status' => 'active',
                'status_reason' => 'migration_backfill',
                'connected_at' => now(),
            ]);
    }

    public function down(string $installer): void {
        if (!SchemaManager::hasTable('meros_integration_connections')) {
            return;
        }

        SchemaManager::table('meros_integration_connections', $installer, function (Blueprint $table) {
            if (Schema::hasColumn('meros_integration_connections', 'last_refreshed_at')) {
                $table->dropColumn('last_refreshed_at');
            }

            if (Schema::hasColumn('meros_integration_connections', 'revoked_at')) {
                $table->dropColumn('revoked_at');
            }

            if (Schema::hasColumn('meros_integration_connections', 'connected_at')) {
                $table->dropColumn('connected_at');
            }

            if (Schema::hasColumn('meros_integration_connections', 'last_error_at')) {
                $table->dropColumn('last_error_at');
            }

            if (Schema::hasColumn('meros_integration_connections', 'last_error')) {
                $table->dropColumn('last_error');
            }

            if (Schema::hasColumn('meros_integration_connections', 'status_reason')) {
                $table->dropColumn('status_reason');
            }

            if (Schema::hasColumn('meros_integration_connections', 'status')) {
                try {
                    $table->dropIndex(['status']);
                } catch (\Throwable $exception) {
                    // Ignore missing index differences across installs.
                }

                $table->dropColumn('status');
            }
        });
    }
};
