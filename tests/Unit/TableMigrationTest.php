<?php

namespace MM\Meros\Tests\Unit;

use MM\Meros\Contracts\Features\Data\Table;
use MM\Meros\Testing\FeatureProvider;
use MM\Meros\Testing\TestCase;

/**
 * Tests table creation, updates, rollback and removal with migration tracking.
 */
final class TableMigrationTest extends TestCase
{
    public function test_table_create_update_rollback_and_drop_are_tracked(): void
    {
        $provider = new FeatureProvider(dirname(__DIR__, 2) . '/src');
        $tracking = Table::__make($provider, ['path' => $provider->getPath('database/tables/001_meros_migrations/create_meros_migrations_table.php')]);
        $tracking->install();
        $this->assertSame('', $tracking->getLastError());
        $this->assertTrue($tracking->isInstalled());
        $fixtureProvider = new FeatureProvider(dirname(__DIR__), 'fixture_tables');
        $table = Table::__make($fixtureProvider, ['path' => $fixtureProvider->getPath('Fixtures/tables/001_test_records/create_test_records_table.php')]);
        $table->install();
        $this->assertSame('', $table->getLastError());
        $this->assertTrue($table->isInstalled());
        $schema = $this->app['db']->connection()->getSchemaBuilder();
        $this->assertTrue($schema->hasColumn('test_records', 'status'));
        $this->assertSame(['create', 'update'], $this->migrationTypes());
        $table->rollback();
        $this->assertSame('', $table->getLastError());
        $this->assertFalse($schema->hasColumn('test_records', 'status'));
        $this->assertSame(['create', 'rollback'], $this->migrationTypes());
        $table->update();
        $this->assertSame('', $table->getLastError());
        $this->assertTrue($schema->hasColumn('test_records', 'status'));
        $this->assertSame(['create', 'update'], $this->migrationTypes());
        $table->uninstall();
        $this->assertSame('', $table->getLastError());
        $this->assertFalse($schema->hasTable('test_records'));
        $this->assertSame([], $this->migrationTypes());
    }

    private function migrationTypes(): array
    {
        return $this->app['db']->table('meros_migrations')
            ->where('related_table', 'test_records')->orderBy('id')->pluck('type')->all();
    }
}
