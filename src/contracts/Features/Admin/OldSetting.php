<?php 

namespace MM\Meros\Contracts\Admin;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

use MM\Meros\Contracts\Data\DataItem;
use MM\Meros\Contracts\FormComponents\Field;

class Setting extends DataItem {
    /**
     * The option group that this setting belongs to.
     *
     * @var string
     */
    protected string $group = '';

    /**
     * The root value of the setting, if it has been retrieved.
     *
     * @var array
     */
    protected array $rootValue = [];

    /**
     * Whether the root value has been loaded from storage during this request.
     *
     * @var bool
     */
    protected bool $hasLoadedRootValue = false;

    /**
     * The settings field instance associated with this setting, if any.
     *
     * @var SettingsField|null
     */
    protected ?SettingsField $settingsField = null;

    /**
     * Whether this setting's string value should be encrypted at rest.
     *
     * @var bool
     */
    protected bool $isEncrypted = false;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->name = $this->identifier;
        $this->hook = 'admin_init';

        if ($this->args['sanitize_callback'] === null) {
            $this->args['sanitize_callback'] = [$this, 'sanitizeForStorage'];
        }

        $defaultArgs = [
            'type'              => 'string',
            'default'           => null,
            'label'             => '',
            'description'       => '',
            'show_in_rest'      => false,
            'sanitize_callback' => $this->args['sanitize_callback'],
        ];

        $this->args = array_merge($defaultArgs, $this->args);

        $this->hook();
    }

    /**
     * Hooks the setting to be loaded via a WordPress hook if all the required properties are set.
     *
     * @return void
     */
    final protected function hook(): void {
        if (empty($this->group) || empty($this->name)) {
            return;
        }

        if ($this->name === 'placeholder_name') {
            return;
        }

        if ($this->isRoot() && !$this->hooked) {
            // Keep direct get_option($this->name) reads decrypted for encrypted
            // nested settings across admin and frontend requests.
            add_filter("option_{$this->name}", [$this, 'decryptOptionValue']);

            add_action($this->hook, function() {
                $this->register();
            });
        }

        $this->hooked = true;
    }

    /**
     * Registers the setting with WordPress. If a field is associated with the setting,
     * it will also register the field. This method is hooked into the 'admin_init' action.
     *
     * @return void
     */
    final protected function register(): void {
        if (
            in_array($this->type, ['array', 'object']) && 
            $this->args['show_in_rest'] ?? false === true
        ) {
            $this->args['show_in_rest'] = ['schema' => $this->toSchema()];
        } // If the setting is an array or object and is set to show in the REST API, convert it to a schema for registration.

        register_setting(
            $this->group,
            $this->name,
            $this->args
        );
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the option group for the setting.
     *
     * @param string $group The option group name.
     * 
     * @return static
     */
    final public function group(string $group): static {
        $this->group = Str::snake($group);

        // Update group for all sub-items if this is the root setting
        if ($this->isRoot() && !empty($this->subItems)) {
            foreach ($this->subItems as $item) {
                $item->group($group);
            }
        }

        $this->hook();
        return $this;
    }

    /**
     * Adds the settings field to a menu page if available.
     * 
     * @param MenuPage|string $page The menu page instance or slug.
     * @param Closure|null    $callback Optional callback to execute when the page is loaded. Pass false to skip creating a page instance if it doesn't exist. Pass null to create a page instance if it doesn't exist.
     * 
     * @return MenuPage
     * @throws \LogicException if the setting does not have an associated settings field.
     */
    final public function page(MenuPage|string $page, ?Closure $callback = null): MenuPage {
        if ($this->settingsField !== null) {
            return $this->settingsField->page($page, $callback);
        }

        throw new \LogicException("Cannot assign page to setting '{$this->name}' because it does not have an associated settings field.");
    }

    /**
     * Assigns the setting's field to a specific settings section.
     *
     * @param SettingsSection|string $section The section instance or a fully-qualified class name or ID.
     * @param Closure|null           $callback Optional callback to execute when the section is loaded.
     *
     * @return SettingsSection
     * @throws \LogicException if the setting does not have an associated settings field.
     */
    final public function section(SettingsSection|string $section, ?Closure $callback = null): SettingsSection {
        if ($this->settingsField !== null) {
            return $this->settingsField->section($section, $callback);
        }

        throw new \LogicException("Cannot assign section to setting '{$this->name}' because it does not have an associated settings field.");
    }

    /**
     * Overrides the field() method from IsAdminFieldRegistrant to ensure a SettingsField is created for this setting.
     * 
     * Creates or retrieves the field instance associated with this setting. 
     * If a field already exists, it will return that instance and optionally apply a callback to it. 
     * 
     * If no field exists, it will create one and optionally apply a callback to it.
     * If no field type if provided, one will be inferred based on the registrant's data type.
     * 
     * A SettingsField instance will also be created for this setting if applicable.
     *
     * @param string|null  $type
     * @param Closure|null $callback
     * @param array        $args
     *
     * @return Field
     */
    final public function field(?string $type = null, ?Closure $callback = null, array $args = []): Field {
        $field = $this->makeField($type);

        $this->makeSettingsField($args);

        if ($callback instanceof Closure) {
            $callback($field);
        }

        return $field;
    }

    /**
     * Overrides attach() method from IsDataRegistrant to ensure group is passed to any sub-items created.
     *
     * @param string $itemClass
     * @param array  $props
     *
     * @return Setting
     */
    final protected function makeSubItem(string $itemClass, array $props = []): Setting {
        return app($itemClass, [
            'provider' => $this->provider,
            'props'    => array_merge([
                'group' => $this->group
            ], $props)
        ]);
    }

    // =========================================================================
    // Settings Field Management
    // =========================================================================

    /**
     * Creates a settings field instance for this setting if applicable. This is used when a field is assigned to the setting, either via the field() method or by attaching an existing field instance.
     *
     * @param array $args Optional arguments to pass to the SettingsField constructor.
     *
     * @return void
     */
    final protected function makeSettingsField(array $args = []): void {
        $makeSettingField = true;

        if ($this->type === 'object') {
            $makeSettingField = false;
        }

        if ($this->parent?->getItemDataType() === 'object') {
            $makeSettingField = false;
        }

        if (!$makeSettingField) {
            return;
        }

        $this->settingsField = new SettingsField(
            provider: $this->provider,
            setting:  $this,
            args:     $args
        );

        $this->field->settingsField($this->settingsField);
    }

    /** 
     * Walk through all sub-items and apply a callback to their setting fields if they exist.
     *
     * @param callable $callback A callback function that takes a SettingField instance as its parameter.
     *
     * @return void
     */
    final protected function walkSettingFields(callable $callback): void {
        $this->walk(function ($item) use ($callback) {
            if ($item->settingsField) {
                $callback($item->settingsField);
            }
        });
    }

    // =========================================================================
    // Value and Database Management
    // =========================================================================

    /**
     * Unregisters the setting if it is a root setting.
     *
     * @return void
     */
    final public function unload(): void {
        if (!$this->isRoot()) {
            return; // Only root settings are registered.
        }

        unregister_setting($this->group, $this->name);
    }

    final public function encrypt(): self {
        if ($this->type !== 'string') {
            throw new \LogicException("Only string settings can be encrypted.");
        }

        $this->isEncrypted = true;

        return $this;
    }

    /**
     * Sanitizes setting payload and encrypts flagged string values for storage.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    final public function sanitizeForStorage(mixed $value): mixed {
        $sanitized = $this->sanitize($value);

        return $this->transformCryptoRecursive($sanitized, 'encrypt');
    }

    /**
     * WordPress option filter callback to transparently decrypt stored payload.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    final public function decryptOptionValue(mixed $value): mixed {
        return $this->transformCryptoRecursive($value, 'decrypt');
    }

    /**
     * Sets the root value of the setting. Used to cache the root value when retrieving nested settings.
     *
     * @param array $value
     *
     * @return self
     * 
     * @throws \LogicException if the setting is not a root setting.
     */
    final public function rootValue(array $value): self {
        if (!$this->isRoot()) {
            throw new \LogicException("Cannot set root value on a non-root setting.");
        }

        $this->rootValue = $value;
        $this->hasLoadedRootValue = true;
        return $this;
    }

    /**
     * Returns the cached root value of the setting. Throws an exception if called on a non-root setting.
     *
     * @return array
     */
    final public function getCachedRootValue(): array {
        if (!$this->isRoot()) {
            throw new \LogicException("Cannot get cached root value from a non-root setting.");
        }

        return $this->rootValue;
    }

    /**
     * Returns whether the root value has already been loaded and cached.
     *
     * @return bool
     */
    final protected function hasCachedRootValue(): bool {
        if (!$this->isRoot()) {
            throw new \LogicException("Cannot check cached root value on a non-root setting.");
        }

        return $this->hasLoadedRootValue;
    }

    /**
     * Retrieves the root value of the setting from the database, or returns the cached root value if it has already been retrieved.
     *
     * @return array
     */
    final public function getRootValue(bool $refresh = false): array {
        $root = $this->getRoot();

        if (!$root instanceof self) {
            throw new \LogicException('Root data registrant must be an instance of Setting.');
        }

        if ($root->hasCachedRootValue() && !$refresh) {
            return $root->transformCryptoRecursive($root->getCachedRootValue(), 'decrypt');
        }

        $default = $root->args['default'] ?? [];

        if ($default === []) {
            $default = $root->getDefault();
        }

        $default = is_array($default) ? $default : [];

        $missingSentinel = "\0__meros_missing_option__\0";
        $storedRaw = get_option($root->name, $missingSentinel);

        if ($storedRaw === $missingSentinel) {
            $value = $default;
        } else {
            $stored = is_array($storedRaw) ? $storedRaw : [];
            $value = $this->mergeDefaultAndStoredValues($default, $stored);
        }

        // Runtime values should remain readable/decrypted.
        $value = $root->transformCryptoRecursive($value, 'decrypt');

        $root->rootValue($value);

        return $value;
    }

    /**
     * Merges default and stored settings values while treating list arrays as
     * replace-only collections.
     *
     * This avoids carrying trailing default list values (for example in
     * multi-select/checkbox arrays) when a shorter saved list exists.
     *
     * @param mixed $default
     * @param mixed $stored
     *
     * @return mixed
     */
    final protected function mergeDefaultAndStoredValues(mixed $default, mixed $stored): mixed {
        if (!is_array($default) || !is_array($stored)) {
            return $stored;
        }

        if (array_is_list($default) || array_is_list($stored)) {
            return $stored;
        }

        $merged = $default;

        foreach ($stored as $key => $storedValue) {
            if (array_key_exists($key, $default)) {
                $merged[$key] = $this->mergeDefaultAndStoredValues($default[$key], $storedValue);
            } else {
                $merged[$key] = $storedValue;
            }
        }

        return $merged;
    }

    /**
     * Retrieves the current value of the setting.
     *
     * @return mixed
     */
    final public function getValue(bool $refresh = false): mixed {
        $value = $this->getRootValue($refresh);

        // If this is the root, return directly
        if ($this->isRoot()) {
            return $value;
        }

        // Traverse into nested structure using path
        $segments = array_values(array_filter(explode('.', $this->path), fn ($segment) => $segment !== ''));

        if ($segments === []) {
            return $this->getDefault();
        }

        $root = $this->getRoot();

        // Remove root segment when path is rooted (e.g. root_name.child)
        if ($root instanceof self && ($segments[0] ?? null) === $root->name) {
            array_shift($segments);
        }

        if ($segments === []) {
            return $this->getDefault();
        }

        return $this->resolvePathValue($value, $segments, $this->getDefault());
    }

    /**
     * Updates the value of the setting in the database.
     *
     * @param mixed $value The new value to set for the setting.
     *
     * @return bool True if the update was successful, false otherwise.
     *
     * @throws \LogicException if the root data registrant is not an instance of Setting.
     */
    final public function updateValue(mixed $value): bool {
        $currentRootValue = $this->getRootValue(true);

        $root = $this->getRoot();

        if (!$root instanceof self) {
            throw new \LogicException('Root data registrant must be an instance of Setting.');
        }

        if ($this->isRoot()) {
            $sanitizedRootValue = $this->sanitize($value);
            $sanitizedRootValue = is_array($sanitizedRootValue) ? $sanitizedRootValue : [];

            $persistedRootValue = $this->transformCryptoRecursive($sanitizedRootValue, 'encrypt');
            $persistedRootValue = is_array($persistedRootValue) ? $persistedRootValue : [];

            update_option($this->name, $persistedRootValue);
            $this->rootValue($sanitizedRootValue);
            return true;
        }

        $segments = array_values(array_filter(explode('.', $this->path), fn ($segment) => $segment !== ''));

        if ($segments === []) {
            return false;
        }

        if ($root instanceof self && ($segments[0] ?? null) === $root->name) {
            array_shift($segments);
        }

        if ($segments === []) {
            return false;
        }

        $newRootValue = $this->setPathValue($currentRootValue, $segments, $value);
        $newRootValue = $root->sanitize($newRootValue);
        $newRootValue = is_array($newRootValue) ? $newRootValue : [];

        $persistedRootValue = $root->transformCryptoRecursive($newRootValue, 'encrypt');
        $persistedRootValue = is_array($persistedRootValue) ? $persistedRootValue : [];

        update_option($root->name, $persistedRootValue);
        $root->rootValue($newRootValue);
        return true;
    }

    /**
     * Recursively applies encryption/decryption to values based on this setting
     * schema and encrypted string sub-items.
     *
     * @param mixed  $value
     * @param string $mode  Either 'encrypt' or 'decrypt'.
     *
     * @return mixed
     */
    final protected function transformCryptoRecursive(mixed $value, string $mode): mixed {
        if (!in_array($mode, ['encrypt', 'decrypt'], true)) {
            return $value;
        }

        if ($this->type === 'string') {
            if (!$this->isEncrypted || !is_string($value) || $value === '') {
                return $value;
            }

            return $this->transformStringCrypto($value, $mode);
        }

        if ($this->type === 'object') {
            if (!is_array($value)) {
                return $value;
            }

            $transformed = $value;

            foreach ($this->subItems as $child) {
                $childName = $child->getName();

                if (!array_key_exists($childName, $transformed)) {
                    continue;
                }

                $transformed[$childName] = $child->transformCryptoRecursive($transformed[$childName], $mode);
            }

            return $transformed;
        }

        if ($this->type === 'array') {
            if (!is_array($value)) {
                return $value;
            }

            // Array of objects
            if ($this->itemType === 'object') {
                $rows = [];

                foreach ($value as $index => $row) {
                    if (!is_array($row)) {
                        $rows[$index] = $row;
                        continue;
                    }

                    $transformedRow = $row;

                    foreach ($this->subItems as $child) {
                        $childName = $child->getName();

                        if (!array_key_exists($childName, $transformedRow)) {
                            continue;
                        }

                        $transformedRow[$childName] = $child->transformCryptoRecursive($transformedRow[$childName], $mode);
                    }

                    $rows[$index] = $transformedRow;
                }

                return $rows;
            }

            return $value;
        }

        return $value;
    }

    /**
     * Encrypts/decrypts a scalar string value and falls back safely on failure.
     *
     * @param string $value
     * @param string $mode
     *
     * @return string
     */
    final protected function transformStringCrypto(string $value, string $mode): string {
        try {
            if ($mode === 'encrypt') {
                return Crypt::encryptString($value);
            }

            return Crypt::decryptString($value);
        } catch (\Throwable $_e) {
            // If value was not encrypted yet (or key changed), keep raw value.
            return $value;
        }
    }

    /**
     * Writes a value at a dot-notated path segment list against a nested payload.
     *
     * @param mixed $value
     * @param array $segments
     * @param mixed $newValue
     *
     * @return mixed
     */
    final protected function setPathValue(mixed $value, array $segments, mixed $newValue): mixed {
        if ($segments === []) {
            return $newValue;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            $rows = is_array($value) ? $value : [];

            if ($segments === []) {
                return $newValue;
            }

            foreach ($rows as $index => $rowValue) {
                $rows[$index] = $this->setPathValue($rowValue, $segments, $newValue);
            }

            return $rows;
        }

        $container = is_array($value) ? $value : [];
        $current = $container[$segment] ?? [];

        $container[$segment] = $this->setPathValue($current, $segments, $newValue);

        return $container;
    }

    /**
     * Resolves a dot-notated path segment list against a nested value.
     *
     * @param mixed $value
     * @param array $segments
     *
     * @return mixed
     */
    final protected function resolvePathValue(mixed $value, array $segments, mixed $fallback = null): mixed {
        if ($segments === []) {
            return $value;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($value)) {
                return $fallback;
            }

            if ($segments === []) {
                return $value;
            }

            $resolved = [];

            foreach ($value as $index => $rowValue) {
                $resolved[$index] = $this->resolvePathValue($rowValue, $segments, $fallback);
            }

            return $resolved;
        }

        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $fallback;
        }

        return $this->resolvePathValue($value[$segment], $segments, $fallback);
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Gets the option group that this setting belongs to.
     *
     * @return string
     */
    public function getGroup(): string {
        return $this->group;
    }

    /**
     * Gets the slug of the admin page that this setting's field belongs to, if a field is associated with the setting.
     *
     * @return string|null
     */
    final public function getPage(): ?string {
        if ($this->settingsField !== null) {
            return $this->settingsField->getPageSlug();
        }

        return null;
    }

    /**
     * Retrieves the settings field associated with this setting, if any.
     *
     * @return SettingsField|null
     */
    final public function getSettingsField(): ?SettingsField {
        return $this->settingsField;
    }
}