<?php

namespace MM\Meros\Tests\Unit {
    use MM\Meros\App\Admin\Pages\MerosForms;
    use MM\Meros\Facades\Admin\Pages;
    use MM\Meros\Foundations\Testing\WordPressState;
    use MM\Meros\Testing\FeatureProvider;
    use MM\Meros\Testing\TestCase;

    final class FormsMenuTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            WordPressState::$hooks = [];
            FormsMenuWordPressState::$menus = [];
            FormsMenuWordPressState::$submenus = [];
            FormsMenuWordPressState::$capabilities = ['read_forms'];
        }

        public function test_forms_menu_registers_a_shared_parent_and_native_taxonomy_link(): void {
            $provider = new FeatureProvider($this->directory);
            Pages::checkout($provider)->makeFrom(MerosForms::class);

            do_action('admin_menu');

            $this->assertSame(['meros-forms'], array_keys(FormsMenuWordPressState::$menus));
            $this->assertSame('Forms', FormsMenuWordPressState::$menus['meros-forms']['menuTitle']);
            $this->assertSame('read_forms', FormsMenuWordPressState::$menus['meros-forms']['capability']);
            $this->assertSame([
                'parent' => 'meros-forms',
                'title' => 'Form Tags',
                'menuTitle' => 'Form Tags',
                'capability' => 'read_forms',
                'slug' => 'edit-tags.php?taxonomy=form-tags&post_type=form',
            ], FormsMenuWordPressState::$submenus[0]);
        }

        public function test_navigation_keeps_native_list_links_and_respects_taxonomy_permissions(): void {
            $provider = new FeatureProvider($this->directory);
            $page = Pages::checkout($provider)->makeFrom(MerosForms::class);
            ob_start();
            $page->render();
            $html = ob_get_clean();

            $this->assertStringContainsString('edit.php?post_type=form"', $html);
            $this->assertStringContainsString('edit.php?post_type=form-template"', $html);
            $this->assertStringContainsString('edit-tags.php?taxonomy=form-tags&amp;post_type=form', $html);

            FormsMenuWordPressState::$capabilities = ['edit_posts', 'manage_categories'];
            ob_start();
            $page->render();
            $html = ob_get_clean();
            $this->assertStringNotContainsString('<a href=', $html);
        }
    }

    final class FormsMenuWordPressState {
        public static array $menus = [];
        public static array $submenus = [];
        public static array $capabilities = [];
    }
}

namespace {
    if (!function_exists('add_menu_page')) {
        function add_menu_page($title, $menuTitle, $capability, $slug, $callback = '', $icon = '', $position = null): string {
            \MM\Meros\Tests\Unit\FormsMenuWordPressState::$menus[$slug] = compact('title', 'menuTitle', 'capability', 'slug', 'callback', 'icon', 'position');
            return 'toplevel_page_' . $slug;
        }
    }
}

namespace MM\Meros\App\Admin\Pages {
    use MM\Meros\Tests\Unit\FormsMenuWordPressState;

    function add_submenu_page(string $parent, string $title, string $menuTitle, string $capability, string $slug): void {
        FormsMenuWordPressState::$submenus[] = compact('parent', 'title', 'menuTitle', 'capability', 'slug');
    }

    function current_user_can(string $capability): bool {
        return in_array($capability, FormsMenuWordPressState::$capabilities, true);
    }

    function admin_url(string $url): string { return 'https://meros.test/wp-admin/' . $url; }
    function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES); }
    function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
}

namespace MM\Meros\Contracts\Features\Admin {
    function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
}
