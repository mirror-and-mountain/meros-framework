<?php

namespace MM\Meros\App\Settings;

use MM\Meros\App\Support\Field;
use MM\Meros\App\Support\Feature;

class SettingField extends Field {
    protected string $hook = 'admin_init';

    // The settings section instance that this field belongs to.
    protected ?SettingsSection $section = null;

    // The settings section id that this field belongs to.
    protected string $sectionId = 'default';

    // The admin page instance that this field belongs to.
    protected ?AdminPage $page = null;

    // The settings page slug that this field belongs to.
    protected string $pageSlug = '';

    /**
     * Sets the field as ready (or not) based on the field's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if ($this->type === '') {
            $this->ready = false;
            return;
        }

        if ($this->id === '') {
            $this->ready = false;
            return;
        }

        if ($this->pageSlug === '') {
            $this->ready = false;
            return;
        }

        if ($this->label === '') {
            $this->ready = false;
            return;
        }

        if ($this->callback === null) {
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
        add_settings_field(
            $instance->id,
            $instance->label,
            $instance->callback,
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
        $option      = $this->registrar->getName();
        $label       = $this->registrar->getLabel();
        $description = $this->registrar->getDescription();

        // Generate HTML for the label
        $html = '<label id="' . esc_attr($option) . '_field_label" for="' . esc_attr($option) . '_field" class="meros-settings-label">' . esc_html($label) . '</label>';
        
        // Generate HTML for the description
        $html .= $description !== ''
            ? '<p class="description">' . esc_html($description) . '</p>'
            : '';
        
        return $html;
    }
}