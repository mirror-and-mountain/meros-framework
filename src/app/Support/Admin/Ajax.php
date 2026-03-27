<?php 

namespace MM\Meros\App\Support\Admin;

use Illuminate\Support\Str;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Facades\Admin;

class Ajax {
    /**
     * The current admin page.
     *
     * @var string
     */
    private string $currentPage = '';

    /**
     * Admin pages compatible with ajax.
     * For enqueing js in the right places.
     *
     * @var array
     */
    private array  $ajaxPages = ['theme_features'];

    private function __construct(string $currentPage) {
        $this->currentPage = $currentPage;

        add_action('wp_ajax_meros_toggle_feature', [$this, 'handleMerosFeatureToggles']);
        add_action('wp_ajax_meros_install_feature', [$this, 'handleMerosFeatureInstallation']);
        add_action('wp_ajax_meros_update_feature', [$this, 'handleMerosFeatureUpdate']);
    }

    /**
     * Sets up a new instance.
     *
     * @param string $currentPage
     * @return self|bool
     */
    public static function init(string $currentPage = ''): self|bool {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return false;
        }

        return new self($currentPage);
    }

    /**
     * Handles toggling features on and off from the theme features page.
     *
     * @return void
     */
    public function handleMerosFeatureToggles(): void {
        $option = sanitize_key($_POST['option'] ?? '');
        $nonce  = $_POST['nonce'] ?? '';

        if (! $option || ! wp_verify_nonce($nonce, 'meros_toggle_' . $option)) {
            wp_send_json_error('Invalid request');
        }

        $current = (bool) get_option($option);
        update_option($option, $current ? '0' : '1');

        $new_value  = (bool) get_option($option);
        $label      = $new_value ? 'Enabled' : 'Enable';
        $next_value = $new_value ? '0' : '1';

        wp_send_json_success([
            'value'      => (int) $new_value,
            'label'      => $label,
            'title'      => $new_value ? 'Disable' : 'Enable',
            'next_value' => $next_value,
            'nonce'      => wp_create_nonce('meros_toggle_' . $option),
        ]);
    }

    /**
     * Handles installing features from the theme features page.
     *
     * @return void
     */
    public function handleMerosFeatureInstallation(): void {
        $feature = sanitize_key($_POST['context'] ?? '');
        $nonce   = $_POST['nonce'] ?? '';

        if (! $feature || ! wp_verify_nonce($nonce, 'meros_install_feature_' . $feature)) {
            wp_send_json_error('Invalid request');
        }

        $result = Admin::runMigrations($feature);

        if (is_string($result)) {
            wp_send_json_error($result);
        }

        $installedTime = Admin::getInstalledTime($feature)->format('d-m-Y H:i:s');
        $message = Str::replace('_', ' ', Str::title($feature)) . ' installed successfully.';

        wp_send_json_success([
            'message' => $message,

            'removeClasses' => [
                '_' . $feature . '_enable_' . $feature . '_package' => ['is-disabled'] 
            ],

            'removeAttributes' => [
                '_' . $feature . '_enable_' . $feature . '_package' => ['disabled'] 
            ],

            'updateInnerHTML' => [
                $feature . '_install' => 'Installed: ' . esc_attr($installedTime)
            ],

            'replaceElement' => [
                'meros_install_feature_' . $feature . '_wrapper' => '<p class="meros-package-info success is-adding">' . esc_html($message) . '</p>'   
            ]
        ]);
    }

    /**
     * Handles updating features from the theme features page.
     *
     * @return void
     */
    public function handleMerosFeatureUpdate(): void {
        $feature = sanitize_key($_POST['context'] ?? '');
        $nonce   = $_POST['nonce'] ?? '';

        if (! $feature || ! wp_verify_nonce($nonce, 'meros_update_feature_' . $feature)) {
            wp_send_json_error('Invalid request');
        }

        $result = Admin::runMigrations($feature);

        if (is_string($result)) {
            wp_send_json_error($result);
        }

        $updatedTime = Admin::getLastUpdatedTime($feature)->format('d-m-Y H:i:s');        
        $message = Str::replace('_', ' ', Str::title($feature)) . ' updated successfully.';

        wp_send_json_success([
            'message' => $message,

            'updateInnerHTML' => [
                $feature . '_update' => 'Last Updated: ' . esc_attr($updatedTime)
            ],

            'replaceElement' => [
                'meros_update_feature_' . $feature . '_wrapper' => '<p class="meros-package-info success is-adding">' . esc_html($message) . '</p>'   
            ]
        ]);
    }

    /**
     * Enqueues assets for admin AJAX functionality based on the current admin page.
     * 
     * @return void
     */
    public function enqueueAssets(): void {
        $assetsPath = Theme::getFrameworkPath() . 'assets/build/admin/ajax/';
        $assetsUri  = Theme::getFrameworkUri() . 'assets/build/admin/ajax/';
        $handle     = 'meros-admin-ajax';

        if (in_array($this->currentPage, $this->ajaxPages, true)) {
            wp_enqueue_script(
                $handle,
                $assetsUri . 'index.js',
                [],
                filemtime($assetsPath . 'index.js'),
                true
            );

            wp_enqueue_style(
                $handle,
                $assetsUri . 'style-index.css',
                [],
                filemtime($assetsPath . 'style-index.css')
            );
        }
    }
}