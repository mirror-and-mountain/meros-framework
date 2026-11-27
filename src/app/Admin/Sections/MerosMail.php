<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosMail extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-mail-section');
        $this->title('Mail');
        $this->description(
            'Configure and manage your mailer settings. <a href="' . admin_url('admin.php?page=meros-theme-settings&subpage=mail-logs') . '">View Mail Logs</a>'
        );
    }
}