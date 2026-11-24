<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class MerosRoleSettings extends SettingsContainer {
    protected function configure(): void {
        $this->name('meros_role_settings');
        $this->label('Meros Role Settings');
        $this->description('Role visibility and capability assignments managed through Meros.');
        $this->page('meros-security');

        $this->add('array', [
            'name' => 'hidden_default_roles',
            'nested_data_type' => 'string',
            'default' => [],
        ]);
        $this->add('object', [
            'name' => 'role_capabilities',
            'default' => [],
        ]);
    }
}
