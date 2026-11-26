<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Select;
use MM\Meros\App\Components\Fields\TextArea;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Data\DataContainer;
use MM\Meros\Contracts\Features\Data\DataItem;
use MM\Meros\Contracts\Features\Integrations\Integration;
use MM\Meros\Contracts\Features\Components\Field;
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
        $container = new Container();
        $container->instance('config', new Repository());
        Container::setInstance($container);
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
        return [$setting, $container->getItems(), $integration, $container];
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

    public function testConfiguredSettingsUseDefaultsAndRespectPerEnvironmentReadOnlyConfiguration(): void {
        config([
            'integrations.label_test' => [
                'allow_configuration' => true,
                'client_id' => 'global-client',
                'environments' => [
                    'production' => [
                        'allow_configuration' => false,
                        'client_id' => 'production-client',
                    ],
                    'sandbox' => [
                        'allow_configuration' => true,
                        'client_id' => 'sandbox-client',
                    ],
                ],
            ],
        ]);

        [$setting, , $integration, $container] = $this->applyIntegrationSetting(
            ['sandbox' => 'Sandbox', 'production' => 'Production'],
            'production'
        );
        (new ReflectionProperty(DataContainer::class, 'cachedValue'))
            ->setValue($container, ['client_id_production' => 'saved-client']);
        $field = (new ReflectionClass(TextArea::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Field::class, 'dataType'))->setValue($field, 'string');
        (new ReflectionProperty(DataItem::class, 'field'))->setValue($setting, $field);

        (new ReflectionMethod(Integration::class, 'configureSettingFromConfig'))
            ->invoke($integration, $setting, 'client_id', 'production');

        self::assertSame('production-client', $setting->getDefault());
        self::assertTrue($field->isReadonly());
        self::assertSame('production-client', $container->getItemValue('client_id_production', true));

        $sandboxSetting = (new ReflectionClass(LabelTestSetting::class))->newInstanceWithoutConstructor();
        $sandboxSetting->initialiseForTest('client_id_sandbox', 'Client ID');
        $sandboxField = (new ReflectionClass(TextArea::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Field::class, 'dataType'))->setValue($sandboxField, 'string');
        (new ReflectionProperty(DataItem::class, 'field'))->setValue($sandboxSetting, $sandboxField);

        (new ReflectionMethod(Integration::class, 'configureSettingFromConfig'))
            ->invoke($integration, $sandboxSetting, 'client_id', 'sandbox');

        self::assertSame('sandbox-client', $sandboxSetting->getDefault());
        self::assertFalse($sandboxField->isReadonly());
    }

    public function testForcedEnabledDisablesSwitchAndPreservesSavedValue(): void {
        config(['integrations.label_test' => ['force_enabled' => true, 'allow_configuration' => true]]);
        [, , $integration, $container] = $this->applyIntegrationSetting(['default' => 'Default'], 'default');
        $setting = (new ReflectionClass(LabelTestSetting::class))->newInstanceWithoutConstructor();
        $setting->initialiseForTest('label_test_enabled', 'Enable integration');
        $field = (new ReflectionClass(Checkbox::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(DataItem::class, 'field'))->setValue($setting, $field);
        (new ReflectionProperty(DataContainer::class, 'cachedValue'))->setValue($container, ['label_test_enabled' => false]);

        (new ReflectionMethod(Integration::class, 'configureEnabledSetting'))
            ->invoke($integration, $setting, $container);
        (new ReflectionProperty(Integration::class, 'merosSettings'))->setValue($integration, $container);
        (new ReflectionProperty(Integration::class, 'enabled'))->setValue($integration, $container->getItemValue('label_test_enabled'));

        self::assertTrue($field->isDisabled());
        self::assertTrue($container->getItemValue('label_test_enabled', true));
        self::assertTrue($integration->isEnabled());
        self::assertTrue($integration->isEnabled(true));
        self::assertFalse($setting->whenUpdated(true, false, 'label_test_enabled', 'settings'));
        self::assertTrue($setting->whenUpdated(false, true, 'label_test_enabled', 'settings'));
    }

    public function testUnforcedEnabledSwitchUsesSavedState(): void {
        foreach ([[], ['force_enabled' => false]] as $configuration) {
            config(['integrations.label_test' => $configuration]);
            [, , $integration, $container] = $this->applyIntegrationSetting(['default' => 'Default'], 'default');
            $setting = (new ReflectionClass(LabelTestSetting::class))->newInstanceWithoutConstructor();
            $setting->initialiseForTest('label_test_enabled', 'Enable integration');
            $field = (new ReflectionClass(Checkbox::class))->newInstanceWithoutConstructor();
            (new ReflectionProperty(DataItem::class, 'field'))->setValue($setting, $field);
            (new ReflectionProperty(DataContainer::class, 'cachedValue'))->setValue($container, ['label_test_enabled' => false]);

            (new ReflectionMethod(Integration::class, 'configureEnabledSetting'))
                ->invoke($integration, $setting, $container);

            self::assertFalse($field->isDisabled());
            self::assertFalse($container->getItemValue('label_test_enabled'));
            self::assertTrue($setting->whenUpdated(true, false, 'label_test_enabled', 'settings'));
        }
    }

    public function testEnvironmentSelectorHonoursSwitchingConfiguration(): void {
        foreach ([
            [['allow_environment_switching' => false], true],
            [['allow_environment_switching' => true], false],
            [[], false],
        ] as [$configuration, $disabled]) {
            config(['integrations.label_test' => $configuration]);
            [, , $integration, $container] = $this->applyIntegrationSetting(
                ['sandbox' => 'Sandbox', 'production' => 'Production'],
                'sandbox'
            );
            $setting = (new ReflectionClass(LabelTestSetting::class))->newInstanceWithoutConstructor();
            $setting->initialiseForTest('label_test_current_environment', 'Current Environment');
            $field = (new ReflectionClass(Select::class))->newInstanceWithoutConstructor();
            (new ReflectionProperty(DataContainer::class, 'cachedValue'))
                ->setValue($container, ['label_test_current_environment' => 'sandbox']);

            (new ReflectionMethod(Integration::class, 'configureEnvironmentSwitch'))
                ->invoke($integration, $setting, $field);

            self::assertSame($disabled, $field->isDisabled());
            self::assertSame('sandbox', $container->getItemValue('label_test_current_environment'));
            self::assertSame(
                $disabled ? 'sandbox' : 'production',
                $setting->whenUpdated('production', 'sandbox', 'label_test_current_environment', 'settings')
            );
        }
    }
}
