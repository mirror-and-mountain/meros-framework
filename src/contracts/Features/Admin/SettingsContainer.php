<?php 

namespace MM\Meros\Contracts\Features\Admin;

use MM\Meros\Contracts\Features\Data\DataContainer;
use MM\Meros\Contracts\Features\StorableItem;

class SettingsContainer extends DataContainer {
    /**
     * The prefix to be used with the container's name (not used with settings containers).
     *
     * @var string
     */
    final protected string $prefix = '';

    /**
     * The option group name for the settings container.
     *
     * @var string
     */
    private string $optionGroup = '';

    /**
     * The alias of the menu page associated with this settings container.
     *
     * @var string
     */
    private string $page = '';

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        if (!empty($this->name)) {
            $this->optionGroup = $this->name . '_group';
        }

        $this->setHook('admin_init');
        $this->setItemClass(Setting::class);
    }

    final protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->updatedHook('pre_update_option_' . $this->name);
    }
    
    // =========================================================================
    // Hooking
    // =========================================================================

    final public function registerContainer(): void {
        $args = [
            'type'              => 'object',
            'label'             => $this->label,
            'description'       => $this->description,
            'default'           => $this->getDefault(),
            'sanitize_callback' => [$this, 'sanitizeValue'],
            'show_in_rest'      => $this->showInRest ? $this->getSchema() : false,
        ];

        if (empty($this->optionGroup) || empty($this->name)) {
            return;
        }

        register_setting($this->optionGroup, $this->name, $args);
    }

    final public function unregisterContainer(): void {
        unregister_setting($this->optionGroup, $this->name);
    }

    // =========================================================================
    // DataItem Management
    // =========================================================================

    protected function afterAdd(StorableItem $item): void {
        if (!($item instanceof Setting)) {
            throw new \InvalidArgumentException("Only instances of Setting can be added to a SettingsContainer.");
        }

        if ($item->hasField()) {
            $itemPage = $item->getPage();

            if (is_string($itemPage) && empty($itemPage) && !empty($this->page)) {
                $item->page($this->page);
            }
        }
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Updates the name of the option group based on the container's name.
     *
     * @return void
     */
    final protected function afterNameSet(): void {
        $this->optionGroup = $this->name . '_group';
    }

    /**
     * Sets the menuPage alias associated with this SettingsContainer.
     *
     * @param string $page The page identifier.
     *
     * @return static
     */
    final public function page(string $page): static {
        $this->page = $page;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Retrieves the option group name for the settings container.
     *
     * @return string The option group name.
     */
    final public function getOptionGroup(): string {
        return $this->optionGroup;
    }

    /**
     * Retrieves the menuPage alias associated with this SettingsContainer.
     *
     * @return string The menuPage alias.
     */
    final public function getPage(): string {
        return $this->page;
    }

    /**
     * Checks if the settings container has any settings with associated fields.
     *
     * @return boolean
     */
    final public function hasSettingsWithFields(): bool {
        foreach ($this->getItems() as $item) {
            if ($item instanceof Setting && $item->hasField()) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Sanitization and Value Processing
    // =========================================================================

    final protected function getRawValue(): array {
        $rawValue = get_option($this->name, $this->getDefault());

        if (!is_array($rawValue)) {
            $rawValue = [];
        }

        return $rawValue;
    }

    final protected function processRawValue(array $rawValue): array {
        return $rawValue;
    }
}