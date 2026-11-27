<?php

namespace MM\Meros\App\Admin\Pages;

use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Facades\Theme;

final class MerosThemeSettings extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $theme = Theme::get();
        $title = $theme->getName() . ' Settings';

        $this->slug('meros-theme-settings');
        $this->title($title);
        $this->menuTitle($title);
        $this->area('options');
        $this->position(0);
        $this->setProvider($theme);

        $this->callback(function () use ($theme) {            
            if ($theme->hasRegisteredTables()) {
                echo '<div style="margin-bottom:2rem;">';
                echo '<h2>Custom Tables</h2>';
                echo '<p>It looks like this theme has registered custom database tables: ';
                echo '<a href="' . admin_url('admin.php?page=meros-theme-settings&tables=meros-theme-settings-tables') . '" title="Manage Custom Database Tables">Manage</a>';
                echo '</p></div>';
                echo '<h2>Settings</h2>';
            }
        });

        $this->subpage(function (Page $page) {
            $page->title('Mail Logs');
            $page->slug('mail-logs');
            $page->addAjaxAction('meros-reset-mail-logs', function () {
                $config = config('logging.channels.meros-mail');
                $logFile = $config['path'] ?? null;

                if ($logFile && File::exists($logFile)) {
                    File::put($logFile, '');
                }

                return wp_send_json_success([
                    'message' => 'Mail logs have been reset.'
                ]);
            });

            $page->callback(function () use ($page) {
                echo '<p><a href="' . admin_url('admin.php?page=meros-theme-settings') . '">Back to theme settings</a></p>';

                $config = config('logging.channels.meros-mail');

                if (($config['driver'] ?? null) !== 'single') {
                    echo '<p>Mail logs are not configured properly.</p>';
                    return;
                }

                $logFile = $config['path'] ?? null;
                if ($logFile === null || !File::exists($logFile)) {
                    echo '<p>No mail log found.</p>';
                    return;
                }

                $content = File::get($logFile);
                if ($content === '') {
                    echo '<p>Mail log is empty.</p>';
                    return;
                }

                echo '<textarea readonly rows="20" wrap="off" aria-label="Mail log" style="width: 100%; overflow: auto;">' . esc_textarea($content) . '</textarea>';
                echo '<button type="button" id="meros-mail-logs-reset" class="button button-primary" data-nonce="' . esc_attr($page->getAjaxNonce('meros-reset-mail-logs')) . '">Reset Mail Logs</button>';
            });
        });
    }
}