<?php

namespace MM\Meros\Traits\Theme;

trait PermalinkManager {
    /**
     * Hides the "Plain" permalink option in WP Admin.
     * 
     * @return void
     */
    private function hidePlainPermalinkOption(): void {
        add_action( 'admin_print_scripts', function() {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const plainOption = document.querySelector('#permalink-input-plain');
                if ( plainOption ) {
                    plainOption.parentElement.remove();
                }
            });
            </script>
            <?php
        });
    }

    /**
     * Ensures that pretty permalinks are enabled.
     * 
     * @return void
     */
    final public function ensurePrettyPermalinks(): void {
        global $wp_rewrite;
        $permalinkStructure = get_option('permalink_structure');
        if (empty($permalinkStructure) || $permalinkStructure === '/') {
            $wp_rewrite->set_permalink_structure('/%postname%/');
            $wp_rewrite->flush_rules();
        }
    }
}