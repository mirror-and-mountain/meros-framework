<?php

namespace MM\Meros\Tests\Support;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Integrations\Integration;

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
