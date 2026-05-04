<?php

namespace MM\Meros\App\Admin\Templates;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

class TabbedSettingsPage extends MenuPageTemplate {
    /**
     * An array of tabs to be displayed on the tabbed page.
     *
     * @var array
     */
    protected array $tabs = [];

    /**
     * The fully-qualified view path for the template's view file.
     *
     * @var string
     */
    protected string $view = 'meros::admin.templates.tabbed-page';

    /**
     * Renders the content of the tabbed page.
     *
     * @return void
     */
    public function render(array $tabs = []): void {
        if (!empty($tabs)) {
            $this->tabs($tabs);
        }

        echo view($this->view, [
            'title'      => $this->pageTitle,
            'pageSlug'   => $this->pageSlug,
            'tabs'       => $this->tabs
        ]);
    }

    /**
     * Adds a single tab to the tabbed page.
     *
     * @param string   $slug     The unique slug for the tab.
     * @param string   $label    The display label for the tab.
     * @param callable $callback A callback function that outputs the content for the tab when rendered.
     *
     * @return void
     */
    public function tab(string $slug, string $label, callable $callback): void {
        $this->tabs[$slug] = [
            'label'    => $label,
            'callback' => $callback
        ];
    }

    /**
     * Adds multiple tabs to the tabbed page at once.
     *
     * @param array $tabs An associative array of tabs, where the key is the tab slug and the value is an array of properties including 'label' and 'callback'.
     *
     * @return void
     */
    public function tabs(array $tabs): void {
        foreach ($tabs as $slug => $props) {
            if (is_string($slug) && is_array($props)) {
                $label    = $props['label'] ?? Str::title(str_replace('-', ' ', $slug));
                $callback = $props['callback'] ?? function() {
                    echo '<p>No content defined for this tab.</p>';
                };

                $this->tabs[$slug] = [
                    'label'    => $label,
                    'callback' => $callback
                ];
            }
        }
    }
}