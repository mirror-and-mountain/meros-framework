<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosFormsFeedbackStyles extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-forms-feedback-styles-section');
        $this->title('Feedback Styles');
        $this->description('Customize the appearance of form feedback messages, including text, background, and border colors.');
    }
}