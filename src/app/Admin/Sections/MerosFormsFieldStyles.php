<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosFormsFieldStyles extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-forms-field-styles-section');
        $this->title('Field Styles');
        $this->description('Customize the appearance of form fields, including background, text, and border colors.');
    }
}