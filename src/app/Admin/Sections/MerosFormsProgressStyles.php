<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosFormsProgressStyles extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-forms-progress-styles-section');
        $this->title('Progress Styles');
        $this->description('Customize the appearance of form progress indicators, including background, text, and border colors.');
    }
}