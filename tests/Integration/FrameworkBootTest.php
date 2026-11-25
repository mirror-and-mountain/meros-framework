<?php

namespace MM\Meros\Tests\Integration;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Theme;
use MM\Meros\Testing\TestTheme;
use MM\Meros\Testing\WordPressTestCase;

/**
 * Tests real Framework and theme provider startup and migrations against the WordPress test database.
 */
final class FrameworkBootTest extends WordPressTestCase
{
    public function test_framework_and_theme_boot_through_real_providers(): void
    {
        $this->assertTrue($this->app->isBooted());
        $this->assertInstanceOf(TestTheme::class, Theme::get());
        $this->assertTrue(Theme::get()->configured);
        $this->assertTrue(did_action('meros_framework_booted') > 0);
        $this->assertSame($this->app['meros.registers.blocks'], $this->app[\MM\Meros\Registers\Components\Blocks::class]);
    }

    public function test_framework_migrations_use_the_wordpress_database(): void
    {
        $this->installProviderTables(Framework::get());
        $this->assertTrue($this->app['db']->connection()->getSchemaBuilder()->hasTable('meros_migrations'));
    }
}
