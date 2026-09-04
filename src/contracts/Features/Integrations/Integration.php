<?php

namespace MM\Meros\Contracts\Features\Integrations;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\Pages\MerosIntegrations as IntegrationsPage;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\MakesItems;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Admin\SettingsContainers;
use MM\Meros\Support\Integrations\HttpClient;

abstract class Integration extends Feature implements Registrable {
    /**
     * The name of the integration, which is used as its identifier.
     *
     * @var string
     */
    final protected string $name;

    /**
     * The category of the integration.
     *
     * @var string
     */
    protected string $category = 'general';

    /**
     * The integration's available environments.
     *
     * @var array
     */
    protected array $environments = [];

    /**
     * The setting name used to determine the integration's current environment.
     *
     * @var string
     */
    protected string $currentEnvironmentSettingName = '';

    /**
     * The currently enabled environment for the setting.
     *
     * @var string
     */
    protected string $currentEnvironment = '';

    /**
     * Whether the integration is enabled or not.
     *
     * @var boolean
     */
    private bool $enabled;

    /**
     * The menu page associated with the integration.
     *
     * @var Page
     */
    final protected Page $menuPage;

    /**
     * The settings container which stores this integration's on/off switch.
     * 
     * @var SettingsContainer
     */
    final protected SettingsContainer $merosSettings;

    /**
     * The settings container associated with the integration.
     *
     * @var SettingsContainer
     */
    final protected SettingsContainer $settings;

    private array $configurableSettings = [
        'baseUrl',
        'clientId',
        'clientSecret',
        'queryableObjects'
    ];

    /**
     * An instance of HttpClient for use when needed.
     * 
     * @var HttpClient
     */
    final protected HttpClient $httpClient;

    private array $lastError = [];

    use IsRegistrable, MakesItems, InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->identifier('name', 'snake');
        $provider = $this->getProvider();
        $providerHandle = $provider->getHandle();

        $this->environments = $this->environments();

        $this->name($providerHandle . class_basename(static::class));
        $this->currentEnvironmentSettingName = $this->getName() . '_current_environment';

        $this->resolveMenuPage();
        $this->resolveSettingsContainer();
        $this->initSettings();
        $this->initEnvironmentSwitch();

        $this->httpClient = new HttpClient();
    }

    /**
     * Returns an array of environments supported by this integration.
     * May be overriden by implementing classes to add additional environements.
     * Environments should be keyed by their handle with a value representing a label e.g.
     * ['production' => 'Production', 'sandbox' => 'Sandbox'] 
     *
     * @return array
     */
    protected function environments(): array {
        return ['default' => 'Default'];
    }

    /**
     * Resolves a Page (MenuPage) instance to display the integration's settings in wp-admin.
     *
     * @return void
     */
    private function resolveMenuPage(): void {
        $integrationsPage = $this->makeItemFrom(IntegrationsPage::class, Page::class);

        if (!($integrationsPage instanceof Page)) {
            throw new \RuntimeException('Integrations page must be an instance of Page.');
        }

        $this->menuPage = $integrationsPage->subpage(function (Page $page) {
            $page->slug($this->getName('slug'));
            $page->callback(function () {
                echo 
                    '<nav class="meros-breadcrumbs" aria-label="Breadcrumb">
                        <a href="' . admin_url('admin.php?page=meros-integrations') . '">Integrations</a>
                        <span class="meros-breadcrumb-separator">/ ' . $this->getLabel() . '</span>
                    </nav>';
            });
        });
    }

    /**
     * Resolves the meros integration settings container to register this integration's on/off switch.
     *
     * @return void
     */
    private function resolveMerosIntegrationSettings(): void {
        $container = SettingsContainers::checkout(Framework::get())
            ->makeFrom('meros_integration_settings');

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException('The meros integrations settings container must be an instance of SettingsContainer');
        }

        $setting = $container->add('boolean', function (Setting $setting) {
            $setting->name($this->getName() . '_enabled');
            $setting->label('Enable ' . $this->getLabel());
            $setting->description($this->getDescription());
            $setting->default(false);
            $setting->field();
        });

        $this->enabled = $container->getItemValue($this->getName() . '_enabled', true);
        $this->merosSettings = $container;

        add_filter('meros_settings_field_title', function (string $title, string $id, Setting $setting) {
            if ($setting->getName() !== $this->getName() . '_enabled') {
                return $title;
            }

            $description = $this->getDescription();

            return  
                '<div class="meros-settings-field-title-wrapper">' .
                    '<label for="' . esc_attr($id) . '">' . esc_html($title) . '</label>' .
                    (!empty($description) ? 
                        '<div class="meros-settings-field-description"><span class="description">' . esc_html($description) . '</span></div>' : ''
                    ) .
                    '<a href="' . esc_url(admin_url('admin.php?page=meros-integrations&integration=' . $this->getName('slug'))) . '" title="Manage">Manage</a>
                </div>';
        }, 10, 3);
    }

    /**
     * Resolves a SettingsContainer instance to store the integration's settings.
     *
     * @return void
     */
    private function resolveSettingsContainer(): void {
        $provider      = $this->getProvider();
        $containerName = 'meros_integration_settings_' . $this->getName();
        $container     = SettingsContainers::checkout($provider)->get($containerName);

        if ($container === null) {
            $container = $this->makeItem(SettingsContainer::class, [
                'name' => $containerName,
                'page' => $this->menuPage->getSlug()
            ]);
        }

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException('The settings container for the integration must implement the SettingsContainer interface.');
        }

        $container->onAdd(function (Setting $setting, SettingsContainer $container) {
            $name = $setting->getName();
            $label = $setting->getLabel();

            if ($name === $this->currentEnvironmentSettingName) {
                return;
            }

            $currentEnv = $this->getCurrentEnvironment();
            
            if (!Str::endsWith($name, '_' . $currentEnv)) {
                $setting->name($name . '_' . $currentEnv);
                $setting->label($label . ' (' . ucfirst($currentEnv) . ')');

                $settingField = $setting->getField();
                if ($settingField !== null) {
                    $newField = clone($settingField);
                    $newField->removeAttribute('data-hidden');
                    $setting->__overrideField($newField);
                }
            }

            $environments = $this->getEnvironments();

            foreach ($environments as $handle => $__) {
                if ($handle === $currentEnv) {
                    continue;
                }

                if (!$container->hasItem($name . '_' . $handle)) {
                    $envSetting = clone($setting);

                    $envSetting->name($name . '_' . $handle);
                    $envSetting->label($label . ' (' . ucfirst($handle) . ')');
                    $container->__pushItem($envSetting);

                    $envSettingField = $envSetting->getField();
                    
                    if ($envSettingField !== null) {
                        $newField = clone($envSettingField);
                        $newField->attribute('data-hidden', true);
                        $envSetting->__overrideField($newField);
                    }
                }
            }
        });

        $this->settings = $container;
    }

    protected function whenConfigured(): void {
        if (empty($this->label)) {
            $this->label = Str::title(Str::replace('_', ' ', $this->name));
        }

        $this->menuPage->title($this->label);
        $this->resolveMerosIntegrationSettings();
        $this->initConfigurableSettings();
        $this->afterInitConfigurableSettings();
        $this->initCustomSettings();
    }

    // =========================================================================
    // Settings Management
    // =========================================================================

    /**
     * Initialises configurable settings added to the integration via traits.
     *
     * @return void
     */
    private function initConfigurableSettings(): void {
        foreach ($this->configurableSettings as $setting) {
            $method = $this->resolveConfigurableSettingMethod($setting);
            if ($method) {
                $this->$method();
            }
        }
    }

    /**
     * Can be used by child classes to perform actions after the integration's configurable settings have been initialised.
     *
     * @return void
     */
    protected function afterInitConfigurableSettings(): void {
        // Intended to be overriden by child classes.
    }

    /**
     * Can be used by implementing classes to add additional settings to the integration.
     *
     * @return void
     */
    protected function initCustomSettings(): void {
        // Intended to be overriden by implementing classes.
    }

    /**
     * Initialises the user-configurable settings for the integration.
     *
     * @return void
     */
    private function initSettings(): void {
        $environments = $this->getEnvironments();
        $switchAction = 'meros_switch_integration_environment';

        $this->settings()->add('string', function (Setting $setting) use ($environments, $switchAction) {
            $setting->name($this->currentEnvironmentSettingName);
            $setting->label('Current Environment');
            $setting->description('The current environment being used for connections to this service.');
            $setting->default(array_key_first($environments));

            if (count($environments) > 1) {
                $setting->field('select', ['options' => $environments])
                    ->attribute('data-meros-integration-env-switch', 'true')
                    ->attribute('data-action', $switchAction)
                    ->attribute('data-nonce', wp_create_nonce($switchAction . '_' . $this->getName()))
                    ->attribute('data-int-name', $this->getName());
            }
        });
    }

    /**
     * Initialises the ajax callback for handling a change to the integration's active environment.
     *
     * @return void
     */
    private function initEnvironmentSwitch(): void {
        $switchAction = "meros_switch_integration_environment_{$this->getName()}";

        add_action("wp_ajax_{$switchAction}", function () use ($switchAction) {
            if (!check_ajax_referer($switchAction, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            $environment = $_POST['env'] ?? null;

            if ($environment === null) {
                wp_send_json_error(['message' => "Couldn't read chosen environment name."], 403);
                exit;
            }

            $this->settings()->setItemValue($this->currentEnvironmentSettingName, $environment);
            wp_send_json_success();
            exit;
        });
    }

    /**
     * Retrieves the value of the given setting name, if provided. 
     * If not the integration's settings container is returned.
     *
     * @param string  $setting Optional. The name of the setting whose value to retrieve.
     * @param boolean $refresh Optional. If $setting is passed, set $refresh to true to retrieve the latest value of the setting from the database.
     *
     * @return mixed The value of the requested setting, if found. Or the integration's settings container.
     */
    final protected function settings(string $setting = '', bool $refresh = false): mixed {
        if (!empty($setting)) {
            if ($setting !== $this->currentEnvironmentSettingName) {
                $setting = $setting . '_' . $this->getCurrentEnvironment();
            } 

            return $this->settings->getItemValue($setting, $refresh);
        }

        return $this->settings;
    }

    /**
     * Returns the active environment for the integration.
     * 
     * @param bool $refresh
     *
     * @return string
     */
    final public function getCurrentEnvironment(bool $refresh = false): string {
        if (empty($this->currentEnvironmentSettingName)) {
            $this->currentEnvironment = 'default';
            return 'default';
        }


        if (!empty($this->currentEnvironment) && !$refresh) {
            return $this->currentEnvironment;
        }

        $this->currentEnvironment = $this->settings($this->currentEnvironmentSettingName, true);
        return $this->currentEnvironment;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolves the configuration method for a configurable setting added to the integration
     * via a trait, if it exists.
     *
     * @param string $setting
     *
     * @return string|null
     */
    final protected function resolveConfigurableSettingMethod(string $setting): ?string {
        $method = 'configure' . ucfirst($setting);
        if (method_exists($this, $method)) {
            return $method;
        }

        return null;
    }

    final protected function logError(string $title, string $message = '', ?int $code = null): void {
        $this->lastError = [
            'title'   => $title,
            'message' => $message,
            'code'    => $code
        ];

        Log::error('Integration (' . $this->getName() . ') logged an error: ', $this->lastError);
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the integration's name, which is used as its identifier.
     *
     * @param string $name
     *
     * @return static
     */
    private function name(string $name): static {
        return $this->setIdentifier($name, false);
    }

    /**
     * Sets the integration's category.
     *
     * @param string $category
     *
     * @return static
     */
    final public function category(string $category): static {
        $this->category = $category;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Retrieves the integration's name, which is used as its identifier.
     *
     * @param string $format
     * 
     * @return string
     */
    final public function getName(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Retrieves the instance of the Page (MenuPage) associated with this integration.
     *
     * @return Page
     */
    final protected function getPage(): Page {
        return $this->menuPage;
    }

    /**
     * Returns the integration's environments array.
     *
     * @return array
     */
    final public function getEnvironments(): array {
        return $this->environments;
    }

    /**
     * Returns whether the integration has any defined environments.
     *
     * @return boolean
     */
    final public function hasEnvironments(): bool {
        return !empty($this->environments);
    }

    /**
     * Returns an instance of HttpClient for making HTTP requests.
     *
     * @return HttpClient
     */
    protected function httpClient(): HttpClient {
        return $this->httpClient;
    }
}