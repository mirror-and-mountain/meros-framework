<?php

namespace MM\Meros\App\Admin\Pages;

use Illuminate\Support\Facades\File;
use MM\Meros\Contracts\Features\Admin\Page;

final class MerosIntegrations extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-integrations');
        $this->title('Integrations');
        $this->menuTitle('Integrations');
        $this->area('options');
        $this->position(1);
        $this->subpageParam('integration');

        $this->subpage(function (Page $page) {
            $page->title('Integrations Log');
            $page->slug('integrations-log');

            $page->addAjaxAction('meros-reset-integrations-log', function () {
                $config  = config('logging.channels.meros-integrations');
                $logFile = $config['path'] ?? null;

                if ($logFile !== null && File::exists($logFile)) {
                    File::put($logFile, '');
                }

                return wp_send_json_success([
                    'message' => 'Integrations log has been reset.'
                ]);
            });

            $page->callback(function () use ($page) {
                echo '<p><a href="' . admin_url('admin.php?page=meros-integrations') . '">Back to integrations</a></p>';

                $config = config('logging.channels.meros-integrations');

                if (($config['driver'] ?? null) !== 'single') {
                    echo '<p>Integrations log is not configured properly.</p>';
                    return;
                }

                $logFile = $config['path'] ?? null;
                if ($logFile === null || !File::exists($logFile)) {
                    echo '<p>No integrations log found.</p>';
                    return;
                }

                $content = File::get($logFile);
                if ($content === '') {
                    echo '<p>Integrations log is empty.</p>';
                    return;
                }

                echo '<textarea readonly rows="20" wrap="off" aria-label="Integrations log" style="width: 100%; overflow: auto;">' . esc_textarea($content) . '</textarea>';
                echo '<button type="button" id="meros-integrations-reset-log" class="button button-primary" data-nonce="' . esc_attr($page->getAjaxNonce('meros-reset-integrations-log')) . '">Reset Integrations Log</button>';
            });
        });

        $this->callback(function () {
            echo '<p>Integrations may record logs of their activity which can be viewed <a href="' . admin_url('admin.php?page=meros-integrations&integration=integrations-log') . '">here</a>.</p><br>';
        });
    }
}