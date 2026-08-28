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
     * A provisional section id or closure to be used when the section is set before the page.
     *
     * @var Closure|string|null
     */
    private Closure|string|null $provisionalSection = null;

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

        if ($field->hasAttribute('data-hidden')) {
            $this->settingsField->hide();
        }

        // Set the field on the SettingsField instance
        $this->settingsField->field($field);

        $this->configureSettingsFieldPage();
        $this->configureSettingsFieldSection();

        // Set the Field instane wrapper for admin settings
        $field->addContext('field-context', 'settings');
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

    /**
     * Sets the description position in the associated SettingsField instance.
     *
     * @param string $position
     *
     * @return static
     */
    final public function descriptionPosition(string $position): static {
        if ($this->settingsField instanceof SettingsField) {
            $this->settingsField->descriptionPosition($position);
        }
        return $this;
    }

    /**
     * Sets the position of the description to after the field in the associated SettingsField instance.
     *
     * @param bool $after Whether to place the description after the field (true) or before (false).
     * @return static
     */
    final public function descriptionAfter(bool $after = true): static {
        if ($this->settingsField instanceof SettingsField) {
            $this->settingsField->descriptionAfter($after);
        }
        return $this;
    }

    // =========================================================================
    // Page Handling
    // =========================================================================

    /**
     * Sets the associated Page for this Setting.
     *
     * @param Page|Closure|string $pageOrClosure   A Page instance or closure to configure the Page. A string can also be provided as a class name or alias to resolve the Page.
     * @param Closure|array       $callbackOrProps Optional closure or array of properties for configuring the Page.
     * @param array               $props           Optional array of properties for configuring the Page.
     *
     * @return static
     */
    final public function page(
        Page|Closure|string $pageOrClosure, 
        Closure|array       $callbackOrProps = [], 
        array               $props = []
    ): static {
        if (is_string($pageOrClosure)) {
            $this->makeAssociationFromClassOrAlias(
                'page', Page::class, $pageOrClosure, $callbackOrProps, $props
            );
        }

        else if ($pageOrClosure instanceof Closure) {
            $this->makeAssociationFromClosure(
                'page', Page::class, $pageOrClosure, $callbackOrProps, $props
            );
        }

        else {
            $this->page = $pageOrClosure;
        }

        if ($this->page instanceof Page && 
            $this->settingsField instanceof SettingsField
        ) {
            $this->settingsField->page($this->page->getSlug());

            if ($this->provisionalSection !== null) {
                $this->section($this->provisionalSection);
                $this->provisionalSection = null;
            }
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
            if (is_string($sectionOrClosure) || $sectionOrClosure instanceof Closure) {
                $this->provisionalSection = $sectionOrClosure;
                return $this;
            }

            throw new \LogicException("Cannot set a section without first setting a menu page.");
        }

        if (is_string($sectionOrClosure)) {
            $classOrAlias = $sectionOrClosure;

            $section = $this->makeAssociationFromClassOrAlias(
                'section', SettingsSection::class, $classOrAlias, $callbackOrProps, $props
            );

            if ($section instanceof SettingsSection) {
                $section->page($this->page->getSlug());
            }
        }

        else if ($sectionOrClosure instanceof Closure) {
            $section = $this->makeAssociationFromClosure(
                'section', SettingsSection::class, $sectionOrClosure, $callbackOrProps, $props
            );

            if ($section instanceof SettingsSection) {
                $section->page($this->page->getSlug());
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

    /**
     * Retrieves the associated SettingsSection for this Setting.
     *
     * @return SettingsSection|null
     */
    final public function getSection(): ?SettingsSection {
        return $this->section;
    }

    /**
     * Checks if the setting has an associated SettingsSection.
     *
     * @return bool
     */
    final public function hasSection(): bool {
        return $this->section instanceof SettingsSection;
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

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Makes an association for a Setting from a class name or alias.
     *
     * @param string $property
     * @param string $contract
     * @param string $classOrAlias
     * @param array  $callbackOrProps
     * @param array  $props
     *
     * @return Page|SettingsSection|null
     */
    private function makeAssociationFromClassOrAlias(
        string         $property,
        string         $contract,
        string         $classOrAlias, 
        Closure|array  $callbackOrProps = [], 
        array          $props = []
    ): Page|SettingsSection|null {
        $item = $this->makeItemFrom(
            $classOrAlias,
            $contract, 
            $callbackOrProps, 
            $props
        );

        if ($item instanceof $contract) {
            $this->{$property} = $item;
        }

        return $item;
    }

    /**
     * Makes an association for a Setting from a closure.
     *
     * @param string  $property
     * @param string  $contract
     * @param Closure $closure
     * @param array   $callbackOrProps
     * @param array   $props
     *
     * @return Page|SettingsSection|null
     */
    private function makeAssociationFromClosure(
        string         $property,
        string         $contract,
        Closure        $closure, 
        Closure|array  $callbackOrProps = [], 
        array          $props = []
    ): Page|SettingsSection|null {
        $props = is_array($callbackOrProps) ? $callbackOrProps : $props;
        $item  = $this->makeItem($contract, $closure, $props);

        if ($item instanceof $contract) {
            $this->{$property} = $item;
        }

        return $item;
    }
}