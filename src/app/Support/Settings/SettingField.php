<?php

namespace MM\Meros\App\Support\Settings;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Feature;

class SettingField extends Feature {
    // The settings section instance that this field belongs to.
    public ?SettingsSection $section = null;

    // The settings section id that this field belongs to.
    public string $sectionId = 'default';

    // The admin page instance that this field belongs to.
    public ?AdminPage $page = null;

    // The settings page slug that this field belongs to.
    public string $pageSlug = '';

    // Optional arguments for the settings field, such as label_for and class.
    public array $args = [];

    public function __construct(
        public FeatureProvider $source,
        public Setting         $setting,
        array  $args = []
    ) {
        $this->args = [
            'label_for' => $args['label_for'] ?? null,
            'class'     => $args['class'] ?? null,
        ];

        add_action('admin_init', function() {
            $this->load($this);
        });
    }
    
    /**
     * Sets the field as ready (or not) based on the field's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if ($this->pageSlug === '') {
            $this->ready = false;
            return;
        }

        if ($this->setting->field === null) {
            $this->ready = false;
            return;
        }

        $this->ready = true;
    }

    /**
     * Registers the setting field with WordPress.
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        $setting = $instance->setting;
        $field  = $setting->field;

        if ($field === null) {
            return;
        }

        add_settings_field(
            $field->id,
            $field->label,
            [$field, 'render'],
            $instance->pageSlug,
            $instance->sectionId,
            $instance->args
        );

        $instance->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Associates the field with a specific settings section.
     *
     * @param  SettingsSection|string $section The section instance or id that this field belongs to.
     *
     * @return self
     */
    public function inSection(SettingsSection|string $section): self {
        if ($section instanceof SettingsSection) {
            $this->section   = $section;
            $this->sectionId = $section->id;
        } 
        
        elseif (is_string($section)) {
            $this->sectionId = $section;
        }

        $this->setReady();
        return $this;
    }

    /**
     * Associates the field with a specific admin page.
     *
     * @param  AdminPage|string $page The page instance or slug that this field belongs to.
     *
     * @return self
     */
    public function onPage(AdminPage|string $page): self {
        if ($page instanceof AdminPage) {
            $this->page     = $page;
            $this->pageSlug = $page->slug;
        }

        elseif (is_string($page)) {
            $this->pageSlug = $page;
        }

        $this->setReady();
        return $this;
    }

    /**
     * Sets additional arguments for the field, such as label_for and class.
     *
     * @param  array $args An associative array of arguments to set for the field.
     *
     * @return self
     */
    public function args(array $args): self {
        $labelFor = $args['label_for'] ?? null;
        $class    = $args['class'] ?? null;

        if (is_string($labelFor)) {
            $this->args['label_for'] = $labelFor;
        }

        if (is_string($class)) {
            $this->args['class'] = $class;
        }

        $this->setReady();
        return $this;
    }

    /***************************
     * Helpers
     ***************************/
    /**
     * Generates HTML for the field title, which includes the label and description.
     *
     * @return string
     */
    protected function getFieldTitleHTML(): string {
        $option      = $this->setting->name;
        $label       = $this->setting->getFieldLabel();
        $description = $this->setting->getFieldDescription();

        // Generate HTML for the label
        $html = '<label id="' . esc_attr($option) . '_field_label" for="' . esc_attr($option) . '_field" class="meros-settings-label">' . esc_html($label) . '</label>';
        
        // Generate HTML for the description
        $html .= $description !== ''
            ? '<p class="description">' . esc_html($description) . '</p>'
            : '';
        
        return $html;
    }
}