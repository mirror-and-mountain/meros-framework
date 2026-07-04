<?php

namespace MM\Meros\Services\Contracts\Admin;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Facades\SettingsSections;

final class SettingsField extends FeatureDefinition {
    /**
     * The MenuPage instance that this field belongs to, if set.
     *
     * @var MenuPage|null
     */
    protected ?MenuPage $pageInstance = null;

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
     * The Field instance that represents the actual form field to be rendered on the settings page.
     *
     * @var Field|null
     */
    protected ?Field $field = null;

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
     * HTML to be used as the field's title.
     *
     * @var string
     */
    protected string $titleHTML = '';

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
        $this->provider = $provider;
        $this->setting  = $setting;
        $this->page     = $this->provider->getSettingsPageSlug();
        $this->args($args);
        $this->queue();
    }
    
    /**
     * Queues the settings field to be loaded via a WordPress hook if all the required properties are set.
     *
     * @return void
     */
    protected function queue(): void {
        $requiredProps = [
            'field',
            'setting',
            'page',
        ];

        foreach ($requiredProps as $prop) {
            if ($this->$prop === null || (is_string($this->$prop) && empty($this->$prop))) {
                return;
            }
        }

        if (!$this->queued) {
             add_action('admin_init', function() {
                $this->load();
            });
        }
        
        $this->queued = true;
    }

    /**
     * Registers the setting field with WordPress.
     *
     * @return void
     */
    protected function load(): void {
        $field   = $this->field;

        if ($this->section === 'default') {
            add_settings_section(
                'default',
                '',
                '__return_null',
                $this->page
            ); // Ensure the default section exists
        }

        $render = function() use ($field) {
            $field->value($this->setting->getValue());
            $field->render(true, [
                'label' => false,
                'helpText' => false
            ]);
        };

        add_settings_field(
            $field->getID(),
            $this->titleHTML === '' ? $this->getFieldTitleHTML() : $this->titleHTML,
            $render,
            $this->page,
            $this->section,
            $this->args
        );
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Associates the field with a specific settings section.
     *
     * @param  SettingsSection|string $section The section instance, a fully-qualified class name, or ID.
     *
     * @return self
     */
    public function section(SettingsSection|string $section, array $props = []): self {
        if ($section instanceof SettingsSection) {
            $this->sectionInstance = $section;
            $this->section = $section->getID();
            $this->page    = $section->getPageSlug();
        } 
        
        else if (is_string($section)) {
            $resolvedSection = null;

            // 1) Resolve by existing section ID first.
            $existingSection = SettingsSections::checkout($this->provider)->get($section);

            if ($existingSection instanceof SettingsSection) {
                $resolvedSection = $existingSection;
            }

            // 2) Resolve by registered key if a matching section wasn't found.
            if ($resolvedSection === null) {
                try {
                    $resolvedSection = SettingsSections::checkout($this->provider)->makeFrom($section, $props);
                } catch (\Throwable $e) {
                    $resolvedSection = null;
                }
            }

            // 3) If a page slug is passed (e.g. meros-features-theme), use default section on that page.
            if ($resolvedSection === null && str_starts_with($section, 'meros-features-')) {
                $this->section = 'default';
                $this->page = $section;
                $this->queue();
                return $this;
            }

            if ($resolvedSection !== null) {
                $this->sectionInstance = $resolvedSection;
                $this->section = $resolvedSection->getID();
                $this->page    = $resolvedSection->getPageSlug();
            }
        }

        $this->queue();
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

        $this->queue();
        return $this;
    }

    /**
     * Attaches a field instance to this settings field for rendering.
     *
     * @param Field|array $field A field instance. Note that settings fields will not accept an array of fields.
     *
     * @return self
     * @throws \InvalidArgumentException if an array of fields is passed instead of a single Field instance.
     */
    public function attachField(Field|array $field): self {
        if (is_array($field)) {
            throw new \InvalidArgumentException('Only a single Field instance can be attached to a SettingsField.');
        }

        $this->field = $field;
        $this->queue();
        return $this;
    }

    /**
     * Adds extra HTML to be used in the field's title.
     *
     * @param string $html The HTML string to use in the field's title.
     *
     * @return self
     */
    public function titleHTML(string $html): self {
        $html = wp_kses_post($html);
        $this->titleHTML = $this->getFieldTitleHTML($html);
        return $this;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Gets the slug of the admin page that this field belongs to.
     *
     * @return string
     */
    public function getPageSlug(): string {
        return $this->page;
    }

    /**
     * Generates HTML for the field title, which includes the label and description.
     *
     * @return string
     */
    protected function getFieldTitleHTML(string $extra = ''): string {
        $option      = $this->setting->name;
        $label       = $this->setting->getLabel();
        $description = $this->setting->getDescription();

        // Generate HTML for the label
        $html = '<label id="' . esc_attr($option) . '_field_label" for="' . esc_attr($option) . '_field" class="meros-settings-label">' . esc_html($label) . '</label>';
        
        // Generate HTML for the description
        $html .= $description !== ''
            ? '<p class="description">' . esc_html($description) . '</p>'
            : '';

        $html .= $extra;
        
        return $html;
    }
}