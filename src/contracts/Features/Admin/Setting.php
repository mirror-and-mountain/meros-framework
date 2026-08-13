<?php

namespace MM\Meros\Contracts\Features\Admin;

use Closure;

use MM\Meros\Contracts\Features\Data\DataItem;
use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Storable;

use MM\Meros\Facades\Admin\SettingsContainers;

use MM\Meros\Contracts\Features\Concerns\MakesItems;

class Setting extends DataItem {
    /**
     * The SettingsField instance associated with this Setting.
     *
     * @var SettingsField|null
     */
    protected ?SettingsField $settingsField = null;

    /**
     * The MenuPage instance or class associated with this Setting.
     *
     * @var MenuPage|string
     */
    protected MenuPage|string $page = '';

    /**
     * The SettingsSection instance or class associated with this Setting.
     *
     * @var SettingsSection|string
     */
    protected SettingsSection|string $section = 'default';

    /**
     * The option group of the setting (inherited from SettingsContainer).
     *
     * @var string
     */
    private string $optionGroup = '';

    use MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function whenConfigured(): void {
        parent::whenConfigured();
        
        if (is_string($this->page) && !empty($this->page)) {
            $this->instantiate('page', MenuPage::class);
        }

        if (is_string($this->section) && $this->section !== 'default' && !empty($this->section)) {
            $this->instantiate('section', SettingsSection::class);
        }

        add_action('admin_init', function () {
            if ($this->page instanceof MenuPage) {
                $this->page->__hasSettings($this->optionGroup);
            }
        });
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the option group for this Setting.
     * 
     * For internal use only. The option group should be set automatically 
     * when the Setting is associated with a SettingsContainer.
     *
     * @param string $optionGroup The option group name.
     *
     * @return static
     */
    final public function __optionGroup(string $optionGroup): static {
        $this->optionGroup = $optionGroup;
        return $this;
    }

    // =========================================================================
    // Container Association
    // =========================================================================

    /**
     * Resolves the associated SettingsContainer for this Setting.
     *
     * @return SettingsContainer|null The associated SettingsContainer instance or null.
     */
    final protected function resolveContainer(): ?SettingsContainer {
        return $this->provider->resolveSettingsContainer(
            SettingsContainers::instance()
        );
    }

    /**
     * Sets the associated SettingsContainer for this Setting.
     *
     * @param Storable $container The SettingsContainer instance to associate with this Setting.
     *
     * @return static
     */
    final public function container(Storable $container): static {
        return $this->settingsContainer($container);
    }

    /**
     * Sets the associated SettingsContainer for this Setting, ensuring that the provided container is of the correct type.
     *
     * @param SettingsContainer $container The SettingsContainer instance to associate with this Setting.
     *
     * @return static
     */
    private function settingsContainer(SettingsContainer $container): static {
        $this->container = $container;
        $this->whenContainerSet();
        return $this;
    }

    /**
     * Sets the optionGroup property using the container's specified option group.
     *
     * @return void
     */
    final protected function whenContainerSet(): void {
        if ($this->container instanceof SettingsContainer) {
            $this->optionGroup = $this->container->getOptionGroup();
        }
    }

    // =========================================================================
    // Field Association
    // =========================================================================

    /**
     * Initialises a SettingsField instance for this Setting and configures 
     * the associated field's wrapper view when the field property is set.
     * 
     * @param Field $field The Field instance that has been set for this Setting.
     *
     * @return void
     */
    final protected function whenFieldSet(Field $field): void {
        if ($this->settingsField === null) {
            $this->settingsField = SettingsField::make($this);
        }

        if (!($this->settingsField instanceof SettingsField)) {
            throw new \LogicException("SettingsField instance is not properly initialised.");
        }

        // Set the field on the SettingsField instance
        $this->settingsField->field($field);

        $this->configureSettingsFieldPage();
        $this->configureSettingsFieldSection();

        // Set the Field instane wrapper for admin settings
        $field->wrapper('meros::forms.field-wrappers.admin-settings');
    }

    /**
     * Configures the associated SettingsField instance with the correct page.
     *
     * @return void
     */
    private function configureSettingsFieldPage(): void {
        // Set the page slug on the SettingsField
        if (is_string($this->page) && !empty($this->page)) {
            $this->settingsField->page($this->page);
        } else if ($this->page instanceof MenuPage) {
            $this->settingsField->page($this->page->getSlug());
        }

        // Fallback to the page alias provided by the container if the page property is not set
        else if (is_string($this->page) && empty($this->page)) {
            $this->page = $this->container instanceof SettingsContainer 
                ? $this->container->getPage() 
                : '';

            $this->settingsField->page($this->page);

            // Instantiate the page.
            $this->page($this->page);
        }
    }

    /**
     * Configures the associated SettingsField instance with the correct section.
     *
     * @return void
     */
    private function configureSettingsFieldSection(): void {
        // Set the section ID on the SettingsField
        if (is_string($this->section) && !empty($this->section)) {
            $this->settingsField->section($this->section);
        } else if ($this->section instanceof SettingsSection) {
            $this->settingsField->section($this->section->getIdentifier());
        }
    }

    // =========================================================================
    // Page Handling
    // =========================================================================

    /**
     * Sets the associated MenuPage for this Setting.
     *
     * @param MenuPage|Closure|string $pageOrClosure The MenuPage instance, class name, or closure to configure the MenuPage.
     * @param Closure|array           $callbackOrProps Optional closure or array of properties for configuring the MenuPage.
     * @param array                   $props Optional array of properties for configuring the MenuPage.
     *
     * @return static
     */
    final public function page(
        MenuPage|Closure|string $pageOrClosure, 
        Closure|array           $callbackOrProps = [], 
        array                   $props = []
    ): static {
        if (is_string($pageOrClosure)) {
            $classOrAlias = $pageOrClosure;

            $page = $this->makeItemFrom(
                $classOrAlias,
                MenuPage::class, 
                $callbackOrProps, 
                $props
            );

            if ($page instanceof MenuPage) {
                $this->page = $page;
            }
        }

        else if ($pageOrClosure instanceof Closure) {
            $closure = $pageOrClosure;
            $props   = is_array($callbackOrProps) ? $callbackOrProps : $props;
            $page    = $this->makeItem(MenuPage::class, $closure, $props);

            if ($page instanceof MenuPage) {
                $this->page = $page;
            }
        }

        else {
            $this->page = $pageOrClosure;
        }

        if ($this->page instanceof MenuPage && 
            $this->settingsField instanceof SettingsField
        ) {
            $this->settingsField->page($this->page->getSlug());
        }

        return $this;
    }

    /**
     * Retrieves the associated MenuPage for this Setting.
     *
     * @return MenuPage|string The associated MenuPage instance or class name.
     */
    final public function getPage(): MenuPage|string {
        return $this->page;
    }

    /**
     * Checks if the setting has an associated MenuPage.
     *
     * @return bool
     */
    final public function hasPage(): bool {
        return $this->page instanceof MenuPage || (is_string($this->page) && !empty($this->page));
    }

    // =========================================================================
    // Section Handling
    // =========================================================================

    /**
     * Sets the associated SettingsSection for this Setting.
     *
     * @param SettingsSection|Closure|string $sectionOrClosure The SettingsSection instance, class name, or closure to configure the SettingsSection.
     * @param Closure|array                  $callbackOrProps Optional closure or array of properties for configuring the SettingsSection.
     * @param array                          $props Optional array of properties for configuring the SettingsSection.
     *
     * @return static
     * @throws \LogicException if the menuPage property is not set before calling this method.
     */
    final public function section (
        SettingsSection|Closure|string $sectionOrClosure, 
        Closure|array                  $callbackOrProps = [], 
        array                          $props = []
    ): static {
        if (!($this->page instanceof MenuPage)) {
            throw new \LogicException("Cannot set a section without first setting a menu page.");
        }

        if (is_string($sectionOrClosure)) {
            $classOrAlias = $sectionOrClosure;

            $section = $this->makeItemFrom(
                $classOrAlias,
                SettingsSection::class, 
                $callbackOrProps, 
                $props
            );

            if ($section instanceof SettingsSection) {
                $this->section = $section;
                $this->section->page($this->page->getSlug());
            }
        }

        else if ($sectionOrClosure instanceof Closure) {
            $closure = $sectionOrClosure;
            $props   = is_array($callbackOrProps) ? $callbackOrProps : $props;
            $section = $this->makeItem(SettingsSection::class, $closure, $props);

            if ($section instanceof SettingsSection) {
                $this->section = $section;
                $this->section->page($this->page->getSlug());
            }
        }

        else {
            $this->section = $sectionOrClosure;
            $this->section->page($this->page->getSlug());
        }

        if ($this->section instanceof SettingsSection && 
            $this->settingsField instanceof SettingsField
        ) {
            $this->settingsField->section($this->section->getIdentifier());
        }

        return $this;
    }
}