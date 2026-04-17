<?php

namespace MM\Meros\Services;

use MM\Meros\Services\Contracts\Feature;
use MM\Meros\Services\Contracts\FeatureProvider;

class SettingsField extends Feature {
    /**
     * The AdminPage instance that this field belongs to, if set.
     *
     * @var AdminPage|null
     */
    protected ?AdminPage $pageInstance = null;

    /**
     * The SettingsSection instance that this field belongs to, if set.
     *
     * @var SettingsSection|null
     */
    protected ?SettingsSection $sectionInstance = null;

    /**
     * The Setting instance that this field is associated with, if set.
     *
     * @var Setting|null
     */
    protected ?Setting $setting = null;

    /**
     * The section ID that this field belongs to. Defaults to 'default' if not set.
     *
     * @var string
     */
    protected string $section = 'default';

    /**
     * The admin page slug that this field belongs to.
     *
     * @var string
     */
    protected string $page = '';

    /**
     * Additional arguments for the field, such as label_for and class, to be passed when registering the field with WordPress.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * SettingsField constructor.
     *
     * @param FeatureProvider $provider
     * @param Setting         $setting
     * @param array           $args
     */
    final public function __construct(
        FeatureProvider $provider,
        Setting         $setting,
        array           $args = []
    ) {
        parent::__construct($provider, $args);
        $this->setting = $setting;
        
        add_action('admin_init', function() {
            if (!$this->ready) {
                return;
            }

            $this->load();
        });
    }
    
    /**
     * Sets the field as ready (or not) based on the field's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if ($this->page === '') {
            $this->ready = false;
            return;
        }

        if ($this->setting === null) {
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
    protected function load(): void {
        $setting = $this->setting;
        $field   = $setting->field();

        add_settings_field(
            $field->getID(),
            $this->getFieldTitleHTML(),
            [$field, 'render'],
            $this->page,
            $this->section,
            $this->args
        );

        $this->loaded = true;
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
            $this->sectionInstance  = $section;
            $this->section = $section->id;
        } 
        
        elseif (is_string($section)) {
            $this->section = $section;
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
            $this->pageInstance = $page;
            $this->page = $page->slug;
        }

        elseif (is_string($page)) {
            $this->page = $page;
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
        $label       = $this->setting->getLabel();
        $description = $this->setting->getDescription();

        // Generate HTML for the label
        $html = '<label id="' . esc_attr($option) . '_field_label" for="' . esc_attr($option) . '_field" class="meros-settings-label">' . esc_html($label) . '</label>';
        
        // Generate HTML for the description
        $html .= $description !== ''
            ? '<p class="description">' . esc_html($description) . '</p>'
            : '';
        
        return $html;
    }
}