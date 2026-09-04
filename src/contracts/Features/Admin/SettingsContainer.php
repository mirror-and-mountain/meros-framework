<?php 

namespace MM\Meros\Contracts\Features\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

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
    protected string $optionGroup = '';

    /**
     * The slug or alias of a menu page associated with this settings container.
     *
     * @var string
     */
    protected string $page = '';

    /**
     * The id of a settings section associated with this settings container.
     *
     * @var string
     */
    protected string $section = '';

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        parent::init();
        
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
            'label'             => $this->getLabel(),
            'description'       => $this->getDescription(),
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
            $itemSection = $item->getSection();

            if ($itemPage === null && !empty($this->page)) {
                $item->page($this->page);
            }

            if ($itemSection === null && !empty($this->section)) {
                $item->section($this->section);
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
     * Sets the page slug or alias associated with this SettingsContainer.
     *
     * @param string $page The page identifier.
     *
     * @return static
     */
    final public function page(string $page): static {
        $this->page = $page;
        return $this;
    }

    /**
     * Sets the id of a settings section associated with this SettingsContainer.
     *
     * @param string $section The id of the settings section.
     *
     * @return static
     */
    final public function section(string $section): static {
        $this->section = $section;
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
     * Retrieves the page slug or alias associated with this SettingsContainer.

     * @return string The page slug or alias.
     */
    final public function getPage(): string {
        return $this->page;
    }

    /**
     * Retrieves the id of a settings section associated with this SettingsContainer.
     *
     * @return string The id of the settings section.
     */
    final public function getSection(): string {
        return $this->section;
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

    /**
     * Returns a collection of settings in the container that should have their values encrypted.
     *
     * @return Collection
     */
    protected function getEncryptedSettings(): Collection {
        $encryptedSettings = $this->getItems(true)->where(function (Setting $item) {
            return $item->isEncrypted();
        });

        return $encryptedSettings;
    }

    /**
     * Retrieves a single encrypted setting by its name if one exists.
     *
     * @param string $name
     *
     * @return Setting|null
     */
    protected function getEncryptedSetting(string $name): ?Setting {
        return $this->getEncryptedSettings()->firstWhere(function (Setting $setting) use ($name) {
            return $setting->getName() === $name && $setting->isEncrypted();
        });
    }

    // =========================================================================
    // Sanitization and Value Processing
    // =========================================================================

    /**
     * Retrieves the raw value of the settings container from the WordPress options table.
     *
     * @return array
     */
    final protected function getRawValue(): array {
        $rawValue = get_option($this->name, $this->getDefault());

        if (!is_array($rawValue)) {
            $rawValue = [];
        }

        return $rawValue;
    }

    /**
     * Processes the raw value retrieved from the WordPress options table.
     *
     * @param array $rawValue The raw value to process.
     *
     * @return array The processed value.
     */
    final protected function processRawValue(array $rawValue): array {
        $value = $rawValue;
        $encryptedItems = $this->getEncryptedSettings();

        if ($encryptedItems->isNotEmpty()) {
            foreach ($encryptedItems as $item) {
                $name = $item->getName();
                $encryptedValue = $value[$name] ?? '';

                if (!is_string($encryptedValue) || empty($encryptedValue)) {
                    continue;
                }

                if (!Crypt::appearsEncrypted($encryptedValue)) {
                    continue;
                }

                $decryptedValue = Crypt::decryptString($encryptedValue);
                $value[$name] = $decryptedValue;
            }
        }

        return $value;
    }

    /**
     * Sets the value of the settings container in the WordPress options table.
     *
     * @param array $value The value to set.
     *
     * @return void
     */
    final protected function setValue(array $value): void {
        update_option($this->name, $value);
    }
    
    final public function getIemValue(string $key, bool $refresh = false): mixed {
        $value = parent::getItemValue($key, $refresh);

        if (is_string($value) && !empty($value)) {
            $isEncrypted = $this->getEncryptedSetting($key) !== null;

            if ($isEncrypted && Crypt::appearsEncrypted($value)) {
                return Crypt::decryptString($value);
            }

            return $value;
        }

        return $value;
    }
}