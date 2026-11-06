<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Facades\Components\Forms;

final class MerosDev extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-dev');
        $this->title('Meros Dev');
        $this->menuTitle('Meros Dev');
        $this->area('tools');
        $this->position(1);

        $this->callback(function () {
            $tabs = [
                'database' => 'Database',
                'mail' => 'Mail',
            ];

            $activeTab = $_GET['tab'] ?? 'database';

            echo '<nav class="nav-tab-wrapper">';
            foreach ($tabs as $tab => $label) {
                $activeClass = $activeTab === $tab ? ' nav-tab-active' : '';
                echo '<a href="?page=meros-dev&tab=' . $tab . '" class="nav-tab' . $activeClass . '">' . $label . '</a>';
            }
            echo '</nav>';

            if ($activeTab === 'database') {
                echo '<h2>Database Tab Content</h2>';
            } elseif ($activeTab === 'mail') {
                $form = Forms::checkout($this->getProvider())->make(function (Form $form) {
                    $form->field('email', [
                        'name'        => 'test_email',
                        'label'       => 'Send A Test Email',
                        'placeholder' => 'e.g. test@example.com',
                        'required'    => true
                    ]);

                    $form->attribute('data-meros-send-test-email-nonce', wp_create_nonce('meros_send_test_email'));

                    $form->onSubmit('meros_send_test_email');
                });

                $siteUrl = get_site_url();

                echo '<h2>Mail Tab Content</h2>';
                echo '<div style="display:flex;width:100%;">';
                echo '<div style="flex: 1;">';
                echo $form->html();
                echo '</div>';
                echo '<div style="flex: 1; margin-left: 20px;">';
                echo '<h3>Test Email</h3>';
                echo '<p>Use this form to send a test email.</p>';
                echo '<p>You can check the result at <a href="' . $siteUrl . '/mailpit" target="_blank">' . $siteUrl . '/mailpit</a></p>';
                echo '</div>';
                echo '</div>';
            }
        });
    }
}