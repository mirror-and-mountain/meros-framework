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
     * The Page instance or class associated with this Setting.
     *
     * @var Page|null
     */
    protected ?Page $page = null;

    /**
     * The SettingsSection instance or class associated with this Setting.
     *
     * @var SettingsSection|null
     */
    protected ?SettingsSection $section = null;

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

        add_action('admin_init', function () {
            if ($this->page instanceof Page) {
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
        return $this->getProvider()->resolveSettingsContainer(
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
        if ($this->page instanceof Page) {
            $this->settingsField->page($this->page->getSlug());
        }

        // Fallback to the page alias provided by the container if the page property is not set
        else if ($this->page === null && $this->container instanceof SettingsContainer) {
            $page = $this->container->getPage();

            if (!empty($page)) {
                $this->settingsField->page($page);
                $this->page($page);
            }
        }
    }

    /**
     * Configures the associated SettingsField instance with the correct section.
     *
     * @return void
     */
    private function configureSettingsFieldSection(): void {
        if ($this->section instanceof SettingsSection) {
            $this->settingsField->section($this->section->getIdentifier());
        }
    }

    // =========================================================================
    // Page Handling
    // =========================================================================

    /**
     * Sets the associated Page for this Setting.
     *
     * @param Page|Closure|string $pageOrClosure   A Page instance or closure to configure the Page. A string can also be provided as a class name or alias to resolve the Page.
     * @param Closure|array           $callbackOrProps Optional closure or array of properties for configuring the Page.
     * @param array                   $props           Optional array of properties for configuring the Page.
     *
     * @return static
     */
    final public function page(
        Page|Closure|string $pageOrClosure, 
        Closure|array           $callbackOrProps = [], 
        array                   $props = []
    ): static {
        if (is_string($pageOrClosure)) {
            $classOrAlias = $pageOrClosure;

            $page = $this->makeItemFrom(
                $classOrAlias,
                Page::class, 
                $callbackOrProps, 
                $props
            );

            if ($page instanceof Page) {
                $this->page = $page;
            }
        }

        else if ($pageOrClosure instanceof Closure) {
            $closure = $pageOrClosure;
            $props   = is_array($callbackOrProps) ? $callbackOrProps : $props;
            $page    = $this->makeItem(Page::class, $closure, $props);

            if ($page instanceof Page) {
                $this->page = $page;
            }
        }

        else {
            $this->page = $pageOrClosure;
        }

        if ($this->page instanceof Page && 
            $this->settingsField instanceof SettingsField
        ) {
            $this->settingsField->page($this->page->getSlug());
        }

        return $this;
    }

    /**
     * Retrieves the associated Page for this Setting.
     *
     * @return Page|null The associated Page instance or null if not set.
     */
    final public function getPage(): ?Page {
        return $this->page;
    }

    /**
     * Checks if the setting has an associated Page.
     *
     * @return bool
     */
    final public function hasPage(): bool {
        return $this->page instanceof Page;
    }

    // =========================================================================
    // Section Handling
    // =========================================================================

    /**
     * Sets the associated SettingsSection for this Setting.
     *
     * @param SettingsSection|Closure|string $sectionOrClosure The SettingsSection instance, or closure to configure the SettingsSection. A string can also be provided as a class name or alias to resolve the SettingsSection.
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
        if (!($this->page instanceof Page)) {
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

    // =========================================================================
    // Value Setting
    // =========================================================================

    /**
     * Sets the value of this Setting in the associated SettingsContainer.
     *
     * @param mixed $value The value to set for this Setting.
     *
     * @return void
     * @throws \BadMethodCallException if the Setting is not associated with a SettingsContainer.
     */
    final public function setValue(mixed $value): void {
        $container = $this->container ?? $this->resolveContainer();

        if (!($container instanceof SettingsContainer)) {
            throw new \BadMethodCallException("The Setting '{$this->name}' must be associated with a SettingsContainer before setting its value.");
        }

        $container->setItemValue($this->name, $value);
    }
}