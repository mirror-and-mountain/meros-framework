<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosFormsButtonStyles extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-forms-button-styles-section');
        $this->title('Button Styles');
        $this->description('Customize the appearance of form buttons, including background, text, and border colors.');
    }
}