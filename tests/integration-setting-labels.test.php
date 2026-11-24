<?php

require __DIR__ . '/../../../autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Data\DataContainer;
use MM\Meros\Contracts\Features\Integrations\Integration;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Registers\Admin\SettingsContainers as SettingsContainersRegister;

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class LabelTestIntegration extends Integration {
    public function initialiseForTest(array $environments, string $current): void {
        $this->identifier('name', 'snake');
        $this->name = 'label_test';
        $this->environments = $environments;
        $this->currentEnvironment = $current;
        $this->currentEnvironmentSettingName = 'label_test_current_environment';
    }
}

class LabelTestSetting extends Setting {
    public function initialiseForTest(string $name, string $label): void {
        $this->identifier('name', 'snake');
        $this->name = $name;
        $this->label = $label;
    }
}

$provider = new class implements FeatureProvider {
    public function get(): static { return $this; }
    public function getHandle(bool $slug = false): string { return 'tests'; }
    public function getName(): string { return 'Tests'; }
    public function getPath(string $subPath = ''): string { return $subPath; }
    public function getUri(string $subPath = ''): string { return $subPath; }
    public function getPreference(string $key, bool $fullPath = true): mixed { return null; }
    public function resolveSettingsContainer(SettingsContainersRegister $register): SettingsContainer {
        throw new LogicException('Not used in this test.');
    }
};

function applyIntegrationSetting(array $environments, string $current, string $name = 'client_id'): array {
    global $provider;

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
    $setting->initialiseForTest($name, 'Client ID');
    $callback = (new ReflectionProperty(DataContainer::class, 'onAddCallback'))->getValue($container);
    $callback($setting, $container);
    return [$setting, $container->getItems()];
}

[$setting, $others] = applyIntegrationSetting(['default' => 'Default'], 'default');
check($setting->getName() === 'client_id_default', 'Single-environment storage key must stay suffixed.');
check($setting->getLabel() === 'Client ID' && $others === [], 'Single-environment label must not have a suffix.');

[$setting, $others] = applyIntegrationSetting(['production' => 'Production'], 'production');
check($setting->getName() === 'client_id_production' && $setting->getLabel() === 'Client ID', 'A single named environment must not add a label suffix.');

[$setting, $others] = applyIntegrationSetting(['sandbox' => 'Sandbox', 'production' => 'Production'], 'sandbox');
check($setting->getName() === 'client_id_sandbox' && $setting->getLabel() === 'Client ID (Sandbox)', 'Multiple environments must retain the active label and key.');
check(count($others) === 1 && $others[0]->getName() === 'client_id_production' && $others[0]->getLabel() === 'Client ID (Production)', 'Multiple environments must retain the inactive label and key.');

[$setting, $others] = applyIntegrationSetting(['sandbox' => 'Sandbox', 'production' => 'Production'], 'production');
check($setting->getName() === 'client_id_production' && $setting->getLabel() === 'Client ID (Production)', 'The selected environment must determine the active label.');
check(count($others) === 1 && $others[0]->getName() === 'client_id_sandbox' && $others[0]->getLabel() === 'Client ID (Sandbox)', 'The other environment must remain available.');

echo "Integration setting label tests passed.\n";
