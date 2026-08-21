<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosSettings extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-theme-settings-section');
        $this->title('Meros Settings');
        $this->description('These settings control the behaviour of the Meros Framework and its features. <a href="https://mirrorandmountain.com/docs/meros-framework/" target="_blank">Learn more</a>.');
    }
}