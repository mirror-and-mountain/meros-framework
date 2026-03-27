<?php 

namespace MM\Meros\App;

final class Context {
    public bool $isAdmin = false;
    public bool $isSite  = false;

    public string $currentPage = '';
    public array  $frameworkAdminPages = ['meros_theme_features', 'meros_packages'];

    public function __construct() {
        $this->isAdmin = \is_admin();
        $this->isSite  = ! $this->isAdmin;

        if ($this->isAdmin) {
            $this->currentPage = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        } else {
            $pageSlug          = \get_post_field('post_name', \get_post());
            $this->currentPage = $pageSlug && $pageSlug !== '' ? sanitize_key($pageSlug) : '';
        }
    }

    public function isAdmin(): bool {
        return $this->isAdmin;
    }

    public function currentPage(): string {
        return $this->currentPage;
    }

    public function isFrameworkPage(): bool {
        return $this->isAdmin && in_array($this->currentPage, $this->frameworkAdminPages);
    }
}