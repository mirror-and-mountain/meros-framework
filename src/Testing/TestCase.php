<?php

namespace MM\Meros\Testing;

use MM\Meros\App\Providers\FrameworkServiceProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Register;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Testing\TestCase as FoundationsTestCase;

/** Isolated contract tests with real Framework registers, without full WordPress boot. */
abstract class TestCase extends FoundationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/wordpress-stubs.php';
        $GLOBALS['wpdb'] = (object) ['prefix' => '', 'collate' => 'utf8mb4_unicode_ci'];
        $this->createApplication();
    }

    protected function createApplication(array $providers = [], array $config = [], array $stubs = []): Application
    {
        $app = parent::createApplication($providers, $config, $stubs);
        // Unit tests exercise registration only; WordPressTestCase runs the full provider lifecycle.
        (new FrameworkServiceProvider($app))->register();
        return $app;
    }

    protected function assertRegistered(Register $register, string $identifier, ?FeatureProvider $provider = null): FeatureDefinition
    {
        $feature = $register->get($identifier, $provider);
        $this->assertInstanceOf($register->getContract(), $feature);
        return $feature;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }
}
