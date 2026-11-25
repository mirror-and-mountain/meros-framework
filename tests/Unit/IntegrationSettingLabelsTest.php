<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Data\DataContainer;
use MM\Meros\Contracts\Features\Integrations\Integration;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Registers\Admin\SettingsContainers as SettingsContainersRegister;
use MM\Meros\Tests\Support\LabelTestIntegration;
use MM\Meros\Tests\Support\LabelTestSetting;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Tests environment-specific integration setting keys and labels for single and multiple environments.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IntegrationSettingLabelsTest extends TestCase {
    protected function setUp(): void {
        require_once dirname(__DIR__) . '/Support/IntegrationSettingLabels.php';
    }

    private function applyIntegrationSetting(array $environments, string $current): array {
        $provider = new class implements FeatureProvider {
            public function get(): static { return $this; }
            public function getHandle(bool $slug = false): string { return 'tests'; }
            public function getName(): string { return 'Tests'; }
            public function getPath(string $subPath = ''): string { return $subPath; }
            public function getUri(string $subPath = ''): string { return $subPath; }
            public function getPreference(string $key, bool $fullPath = true): mixed { return null; }
            public function resolveSettingsContainer(SettingsContainersRegister $register): SettingsContainer {
                throw new \LogicException('Not used in this test.');
            }
        };
        $container = (new ReflectionClass(SettingsContainer::class))->newInstanceWithoutConstructor();
        $integration = (new ReflectionClass(LabelTestIntegration::class))->newInstanceWithoutConstructor();
        $integration->initialiseForTest($environments, $current);
        $integration->setProvider($provider);

        $register = new class($container) {
            public function __construct(private SettingsContainer $container) {}
            public function checkout(FeatureProvider $provider): static { return $this; }
            public function get(string $name): SettingsContainer { return $this->container; }
        };
        Facade::setFacadeApplication(new Container());
        Facade::clearResolvedInstances();
        Facade::getFacadeApplication()->instance('meros.registers.settings_containers', $register);
        (new ReflectionMethod(Integration::class, 'resolveSettingsContainer'))->invoke($integration);

        $setting = (new ReflectionClass(LabelTestSetting::class))->newInstanceWithoutConstructor();
        $setting->initialiseForTest('client_id', 'Client ID');
        $callback = (new ReflectionProperty(DataContainer::class, 'onAddCallback'))->getValue($container);
        $callback($setting, $container);
        return [$setting, $container->getItems()];
    }

    public function testSingleEnvironmentKeepsSuffixedKeyWithoutLabelSuffix(): void {
        foreach (['default' => 'Default', 'production' => 'Production'] as $name => $label) {
            [$setting, $others] = $this->applyIntegrationSetting([$name => $label], $name);
            self::assertSame('client_id_' . $name, $setting->getName());
            self::assertSame('Client ID', $setting->getLabel());
            self::assertSame([], $others);
        }
    }

    public function testMultipleEnvironmentsUseActiveAndInactiveLabels(): void {
        foreach (['sandbox' => 'production', 'production' => 'sandbox'] as $active => $inactive) {
            [$setting, $others] = $this->applyIntegrationSetting(
                ['sandbox' => 'Sandbox', 'production' => 'Production'],
                $active
            );
            self::assertSame('client_id_' . $active, $setting->getName());
            self::assertSame('Client ID (' . ucfirst($active) . ')', $setting->getLabel());
            self::assertCount(1, $others);
            self::assertSame('client_id_' . $inactive, $others[0]->getName());
            self::assertSame('Client ID (' . ucfirst($inactive) . ')', $others[0]->getLabel());
        }
    }
}
