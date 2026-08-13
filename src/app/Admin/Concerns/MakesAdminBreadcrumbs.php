<?php 

namespace MM\Meros\App\Admin\Concerns;

trait MakesAdminBreadcrumbs {
    /**
     * Generates the HTML for the breadcrumb navigation in the provider settings page.
     *
     * @param string $rootUrl   The URL of the root page (e.g., Packages or Themes).
     * @param string $rootLabel The root label of the breadcrumb (e.g., 'Packages' or 'Themes').
     * @param string $type      The type of provider (e.g., 'package', 'theme').
     * @param string $handle    The handle of the provider.
     * @param string $label     The label for the provider.
     * @param string $area      The current area ('db' for database tables, 'settings' for settings).
     *
     * @return string The generated HTML for the breadcrumb navigation.
     */
    private function getProviderSettingsBreadcrumbHTML(string $rootUrl, string $rootLabel, string $type, string $handle, string $label, string $area) {
        return '<p><strong><a href="' . esc_url($rootUrl) . '">' . esc_html($rootLabel) . '</a></strong> &raquo; <strong>' . ($area === 'db' ? '<a href="' . esc_url($rootUrl . '&' . $type . '=' . $handle) . '">' . esc_html(ucfirst($label)) . '</a> &raquo;' : esc_html(ucfirst($label))) . '</strong>' . ($area === 'db' ? ' Database Tables' : '') . '</p>';
    }
}