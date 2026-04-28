<?php

namespace MM\Meros\Services\Admin\Templates;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\MenuPageTemplate;

use MM\Meros\Facades\SettingsFields;
use MM\Meros\Facades\SettingsSections;

class TabbedSettingsPage extends MenuPageTemplate {
    /**
     * An array of tabs to be displayed on the tabbed page.
     *
     * @var array
     */
    protected array $tabs = [];

    /**
     * Renders the content of the tabbed page.
     *
     * @return void
     */
    public function render(): void {
        $tabs = [];

        foreach ($this->tabs as $slug => $label) {
            SettingsFields::all(false)->map(function ($field) use ($slug, $label, &$tabs) {
                $pageSlug = $field->getPageSlug();
                if ($pageSlug === $this->pageSlug . '_' . $slug) {
                    $tabs[$slug] = $label;
                }
            });

            SettingsSections::all(false)->map(function ($section) use ($slug, $label, &$tabs) {
                $pageSlug = $section->getPageSlug();
                if ($pageSlug === $this->pageSlug . '_' . $slug) {
                    $tabs[$slug] = $label;
                }
            });
        }

        echo view('meros::admin.templates.tabbed-settings-page', [
            'title'      => $this->title,
            'pageSlug'   => $this->pageSlug,
            'tabs'       => $this->tabs
        ]);
    }

    /**
     * Adds a tab to the tabbed settings page.
     *
     * @param string $tab The name of the tab to add.
     * @return void
     */
    public function tab(string $tab): void {
        $this->tabs[Str::slug($tab)] = Str::title($tab);
    }

    /**
     * Adds multiple tabs to the tabbed page.
     *
     * @param array $tabs An array of tab names to add. Can be an associative array where the key is the tab slug and the value is the tab title, or a simple indexed array of tab names.
     * @return void
     */
    public function tabs(array $tabs): void {
        foreach ($tabs as $key => $tab) {
            if (is_string($key) && is_string($tab)) {
                $this->tabs[Str::slug($key)] = Str::title($tab);
                continue;
            }

            else if (is_int($key) && is_string($tab)) {
                $this->tabs[Str::slug($tab)] = Str::title($tab);
                continue;
            }
        }
    }
}