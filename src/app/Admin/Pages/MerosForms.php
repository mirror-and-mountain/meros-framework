<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class MerosForms extends Page {
    /**
     * Configures the shared Forms menu and its navigation page.
     *
     * @return void
     */
    protected function configure(): void {
        $this->slug('meros-forms');
        $this->title('Forms');
        $this->menuTitle('Forms');
        $this->capability('read_forms');
        $this->icon('dashicons-feedback');
        $this->position(26);
        $this->hideSettings();

        add_action('admin_menu', [$this, 'registerTaxonomyMenu'], 20);

        $this->callback(function () {
            echo '<p>Manage forms, form templates and their shared tags.</p>';
            echo '<ul>';
            foreach ([
                ['edit.php?post_type=form', 'All Forms', 'read_forms'],
                ['edit.php?post_type=form-template', 'Form Templates', 'read_forms'],
                ['edit-tags.php?taxonomy=form-tags&post_type=form', 'Form Tags', 'read_forms'],
            ] as [$url, $label, $capability]) {
                if (!current_user_can($capability)) {
                    continue;
                }

                echo '<li><a href="' . esc_url(admin_url($url)) . '">' . esc_html($label) . '</a></li>';
            }
            echo '</ul>';
        });
    }

    /**
     * Adds the shared taxonomy's native WordPress screen to the Forms menu.
     *
     * @return void
     */
    public function registerTaxonomyMenu(): void {
        add_submenu_page(
            $this->getSlug(),
            'Form Tags',
            'Form Tags',
            'read_forms',
            'edit-tags.php?taxonomy=form-tags&post_type=form'
        );
    }
}
