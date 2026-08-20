<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use MM\Meros\App\BaseTheme;
use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\Pages\ThemeSettings as ThemeSettingsPage;
use MM\Meros\App\Admin\Pages\PackageSettings as PackageSettingsPage;
use MM\Meros\App\Admin\Pages\AssetSettings as AssetSettingsPage;

use MM\Meros\App\Admin\Settings\Containers\FrameworkSettings;
use MM\Meros\App\Admin\Settings\Containers\ThemeSettings;
use MM\Meros\App\Admin\Settings\Containers\PackageSettings;
use MM\Meros\App\Admin\Settings\Containers\AssetGroupSettings;

use MM\Meros\App\Components\Fields\Text;
use MM\Meros\App\Components\Fields\Number;
use MM\Meros\App\Components\Fields\Checkbox;
use MM\Meros\App\Components\Fields\Email;
use MM\Meros\App\Components\FieldGroups\SimpleContact;

use MM\Meros\App\Assets\MFormsDeps;
use MM\Meros\App\Assets\MForms;
use MM\Meros\App\Assets\Admin as MerosAdminAssets;

use MM\Meros\Contracts\Providers\Concerns\IsFrameworkProvider;
use MM\Meros\Contracts\Providers\Concerns\IsNonPackageProvider;

final class Framework extends Provider {
    private array $fields = [
        'text'     => Text::class,
        'number'   => Number::class,
        'checkbox' => Checkbox::class,
        'email'    => Email::class,
    ];

    private array $fieldGroups = [
        'simple-contact-fields' => SimpleContact::class,
    ];

    use IsFrameworkProvider, IsNonPackageProvider;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        // Set framework identity
        $this->setHandle('meros_framework');
        $this->setName('Meros Framework');
        $this->setAuthor('Meros');
        $this->setAuthorUrl('https://mirrorandmountain.com');
        $this->setSupportUrl('https://mirrorandmountain.com/support');

        $themeDir = \get_stylesheet_directory();
        $themeUri = \get_stylesheet_directory_uri();

        $this->setPath($themeDir . '/vendor/mirror-and-mountain/meros-framework/src');
        $this->setUri($themeUri . '/vendor/mirror-and-mountain/meros-framework/src');

        // Set framework preferences
        $this->setPreference('livewire_namespace', 'MM\\Meros\\App\\Livewire');

        // Configure MEROS_ENVIRONMENT services for local development
        if (getenv('MEROS_ENVIRONMENT') && getenv('MEROS_ENVIRONMENT') === 'true') {
            $this->configureLocalMailTransport();
        }

        // Register tables
        $this->tables()->register();
    }

    /**
     * Configures the framework's features, settings and menu pages.
     *
     * @return void
     */
    public function configure(): void {
        $this->registerComponents();
        $this->registerMenuPages();
        $this->registerSettingsContainers();
        $this->registerSettings();
        $this->registerPostTypes();
        $this->registerAssets();
        $this->registerTables();
    }

    /**
     * Used here to register actions for when the theme is activated or deactivated.
     * Specifically, the framework will install its migrations tracking table on activation.
     *
     * @return void
     */
    public function whenConfigured(): void {
        // Fires when the theme is activated, triggering any necessary setup actions.
        add_action('after_switch_theme', function () {
            $this->__whenThemeActivated();
        });

         // Fires when the theme is deactivated, triggering any necessary cleanup actions.
        add_action('switch_theme', function () {
            $this->__whenThemeDeactivated();
        });
    }

    // =========================================================================
    // Form Components
    // =========================================================================

    /**
     * Registers the framework's components.
     *
     * @return void
     */
    private function registerComponents(): void {
        foreach ($this->fields as $alias => $fieldClass) {
            $this->fields()->register($fieldClass, $alias);
        }

        foreach ($this->fieldGroups as $alias => $groupClass) {
            $this->fieldGroups()->register($groupClass, $alias);
        }
    }

    // =========================================================================
    // Settings Management
    // =========================================================================

    /**
     * Resolves the settings container for the framework.
     *
     * @param SettingsContainers $register The SettingsContainers register.
     *
     * @return SettingsContainer The settings container for the framework.
     */
    public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        return $register->get('meros_framework_settings', null, false) ?? 
               $register->makeFrom('meros_framework_settings');
    }

    /**
     * Registers the framework's wp-admin menu pages.
     *
     * @return void
     */
    private function registerMenuPages(): void {
        $this->menuPages()->register(PackageSettingsPage::class, 'meros-packages');
        $this->menuPages()->register(ThemeSettingsPage::class, 'meros-theme-settings', BaseTheme::class);
        $this->menuPages()->register(AssetSettingsPage::class, 'meros-assets');
    }

    /**
     * Registers the framework's settings containers.
     *
     * @return void
     */
    private function registerSettingsContainers(): void {
        $this->settingsContainers()->register(FrameworkSettings::class, 'meros_framework_settings');
        $this->settingsContainers()->register(ThemeSettings::class, 'meros_theme_settings', BaseTheme::class);
        $this->settingsContainers()->register(PackageSettings::class, 'meros_package_settings');
        $this->settingsContainers()->register(AssetGroupSettings::class, 'meros_asset_group_settings');
    }

    /**
     * Registers the framework's settings.
     *
     * @return void
     */
    private function registerSettings(): void {
        $packageToggleSetting = $this->settings()->add('boolean', function ($setting) {
            $setting->name('meros_package_toggled');
            $setting->default(false);
        });

        add_action('meros_packages_registered', function (Collection $packages) use ($packageToggleSetting) {
            $this->registerPackageSettings($packages, $packageToggleSetting);

            if ($packageToggleSetting->getValue() === true && $packageToggleSetting instanceof Setting) {
                flush_rewrite_rules(); // Ensure that any new rewrite rules are applied after packages have been toggled.
                $packageToggleSetting->setValue(false);
            }
        });
    }

    /**
     * Registers on/off toggle settings for all installed packages.
     *
     * @return void
     */
    private function registerPackageSettings(Collection $packages, Setting $packageToggleSetting): void {
        if ($packages->isEmpty()) {
            return;
        }

        $container = $this->settingsContainers('meros_package_settings')
            ?? $this->settingsContainers()->makeFrom('meros_package_settings');

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException("The settings container for the framework must be an instance of SettingsContainer.");
        }

        foreach ($packages as $package) {
            $container->add('boolean', function ($setting) use ($package) {
                $setting->addContext('is_meros_package_setting', true);
                $setting->setProvider($package);
                $setting->name($package->getHandle() . '_enabled');
                $setting->label('Enable ' . $package->getName());
                $setting->default(false);
                $setting->field();
                $setting->onUpdate(function ($value, $oldValue, $itemName, $optionName) use ($package) {
                    if ($value === $oldValue) {
                        return;
                    }

                    if ($value === true) {
                        $package->__whenEnabled();
                    } else {
                        $package->__whenDisabled();
                    }
                });
            });

            add_action('meros_package_enabled_' . $package->getHandle(), function ($package) use ($packageToggleSetting) {
                $packageToggleSetting->setValue(true);
            });

            add_action('meros_package_disabled_' . $package->getHandle(), function ($package) use ($packageToggleSetting) {
                $packageToggleSetting->setValue(true);
            });
        }

        add_filter('meros_settings_field_title', function (string $title, string $id, Setting $setting) {        
            if (!$setting->getContext('is_meros_package_setting')) {
                return $title;
            }

            $package = $setting->getProvider();

            if (!($package instanceof Package)) {
                return $title;
            }

            $slug = Str::slug(Str::replace('_', '-', $package->getHandle()));
            $description = $package->getDescription();

            $hasSettingsFields = $package->hasSettingsWithFields();
            $hasTables = $package->hasRegisteredTables();


            return 
                '<div class="meros-settings-field-title-wrapper">' .
                    '<label for="' . esc_attr($id) . '">' . esc_html($title) . '</label>' .
                    (!empty($description) ? 
                        '<div class="meros-settings-field-description"><span class="description">' . esc_html($description) . '</span></div>' : ''
                    ) .
                    ($hasSettingsFields || $hasTables ? 
                        '<div class="meros-settings-field-actions">' .
                            ($hasSettingsFields ? 
                                '<a href="' . esc_url(admin_url('admin.php?page=meros-packages&package=' . $slug)) . '" title="Settings">Settings</a>' . ($hasTables ? ' | ' : '') : ''
                            ) .
                            ($hasTables ? 
                                '<a href="' . esc_url(admin_url('admin.php?page=meros-packages&package=' . $slug . '&tables=' . $slug . '-tables')) . '" title="Manage Tables">Manage Tables</a>' : ''
                            ) .
                        '</div>' : ''
                    ) .
                '</div>';

        }, 10, 3);
    }

    /**
     * Retrieves the theme's registered settings values.
     * 
     * For internal use only.
     *
     * @param boolean $refresh
     *
     * @return array
     */
    public function __getThemeSettings(bool $refresh = false): array {
        return $this->getContainerSettings('meros_theme_settings', $refresh);
    }

    /**
     * Retrieves the framework's package settings values.
     * 
     * For internal use only.
     *
     * @param boolean $refresh
     *
     * @return array
     */
    public function __getPackageSettings(bool $refresh = false): array {
        return $this->getContainerSettings('meros_package_settings', $refresh);
    }

    /**
     * Retrieves the value of a specific settings container.
     *
     * @param string  $container
     * @param boolean $refresh
     *
     * @return array
     */
    private function getContainerSettings(string $container, bool $refresh = false): array {
        $container = $this->settingsContainers($container) 
            ?? $this->settingsContainers()->makeFrom($container);

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException("The settings container for the framework must be an instance of SettingsContainer.");
        }

        return $container->getValue($refresh);
    }

    // =========================================================================
    // Post Types
    // =========================================================================

    private function registerPostTypes(): void {
        $this->registerCorePostTypes();

        $this->fieldGroups()->make(function ($group) {
            $group->id('test-field-group');
            $group->title('Test Field Group');
            $group->description('A test field group for the test post type.');
            $group->field('text', function ($field) {
                $field->name('test_field');
                $field->label('Test Field');
                $field->default('Default Value');
            });

            $group->field('number', function ($field) {
                $field->name('test_number_field');
                $field->label('Test Number Field');
                $field->default(59);
            });
        });

        $this->postTypes('post')->fields('simple-contact-fields'); // Should resolve the existing one.

        $this->postTypes()->make(function ($postType) {
            $postType->name('tests');
            $postType->label('Test Post Type', 'Test Post Types');
            $postType->public(true);
            $postType->fields('test-field-group'); // Should resolve the existing one.

            $postType->meta(function ($meta) {
                $meta->name('test_meta_container');
                $meta->label('Test Meta Container');
                $meta->description('A test meta container for the test post type.');
                $meta->add('string', function ($item) {
                    $item->name('test_meta_field');
                    $item->label('Test Meta Field');
                    $item->default('Default Meta Value');
                    $item->field();
                });
            });
        });
    }

    /**
     * Registers WordPress core post types (posts and pages) for the framework.
     * 
     * This is so users can add custom fields to core post types using the framework's api.
     *
     * @return void
     */
    private function registerCorePostTypes(): void {
        $this->postTypes()->make(function ($postType) {
            $postType->name('post');
            $postType->core(true);
        });

        $this->postTypes()->make(function ($postType) {
            $postType->name('page');
            $postType->core(true);
        });
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Registers the framework's asset groups and assets.
     *
     * @return void
     */
    private function registerAssets(): void {
        $this->assetGroups()->makeFrom(MFormsDeps::class, 'meros_forms_dependencies');
        $this->assetGroups()->makeFrom(MForms::class, 'meros_forms_assets');
        $this->assetGroups()->makeFrom(MerosAdminAssets::class, 'meros_admin_assets');

        // add_action('admin_init', function () {
        //     dd($this->assetGroups()->all());
        // }, 20);
    }

    // =========================================================================
    // Tables & Migrations
    // =========================================================================

    private function registerTables(): void {
        $this->tables()->register();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns the URI to the framework's image resources.
     * 
     * @param string $path Optional. A relative path to an image within the framework's resources/img directory.
     *
     * @return string
     */
    public function img(string $path = ''): string {
        return $this->getUri() . 'resources/img/' . ltrim($path, '/');
    }

    /**
     * Configures wp_mail to use Mailpit SMTP for local development without relying on SMTP plugins.
     *
     * Set MEROS_MAIL_HOST / MEROS_MAIL_PORT to override defaults.
     *
     * @return void
     */
    private function configureLocalMailTransport(): void {
        add_action('phpmailer_init', function ($phpmailer) {
            $host = getenv('MEROS_MAIL_HOST') ?: 'mailpit';
            $port = (int) (getenv('MEROS_MAIL_PORT') ?: 1025);

            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->Port = $port;
            $phpmailer->SMTPAuth = false;
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        });
    }
}
