<?php

namespace MM\Meros\App;

use Illuminate\Support\Collection;
use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\Pages\ThemeSettings as ThemeSettingsPage;
use MM\Meros\App\Admin\Pages\PackageSettings as PackageSettingsPage;
use MM\Meros\App\Admin\Pages\AssetSettings as AssetSettingsPage;

use MM\Meros\App\Admin\SettingsContainers\FrameworkSettings;
use MM\Meros\App\Admin\SettingsContainers\PackageSettings;
use MM\Meros\App\Admin\SettingsContainers\ThemeSettings;
use MM\Meros\App\Admin\SettingsContainers\AssetGroupSettings;

use MM\Meros\App\FormComponents\Fields\Text;
use MM\Meros\App\FormComponents\Fields\Number;
use MM\Meros\App\FormComponents\Fields\Checkbox;
use MM\Meros\App\FormComponents\Fields\Email;
use MM\Meros\App\FormComponents\FieldGroups\SimpleContact;

use MM\Meros\App\Assets\MerosAdminAssets;

use MM\Meros\Contracts\Providers\Concerns\IsFrameworkProvider;

final class Framework extends Provider {

    use IsFrameworkProvider;

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
    }

    /**
     * Configures the framework's features, settings and menu pages.
     *
     * @return void
     */
    public function configure(): void {
        $this->registerFormComponents();
        $this->registerMenuPages();
        $this->registerSettingsContainers();
        $this->registerSettings();
        $this->registerPostTypes();
        $this->registerAssets();
    }

    // =========================================================================
    // Form Components
    // =========================================================================

    /**
     * Registers the framework's form components.
     *
     * @return void
     */
    private function registerFormComponents(): void {
        $this->fields()->register(Text::class, 'text');
        $this->fields()->register(Number::class, 'number');
        $this->fields()->register(Checkbox::class, 'checkbox');
        $this->fields()->register(Email::class, 'email');

        $this->fieldGroups()->register(SimpleContact::class, 'simple-contact-fields');
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
        return $register->get('meros_framework_settings', $this) ?? 
               $register
                ->checkout($this)
                ->makeFrom('meros_framework_settings');
    }

    /**
     * Registers the framework's wp-admin menu pages.
     *
     * @return void
     */
    private function registerMenuPages(): void {
        $this->menuPages()->register(PackageSettingsPage::class, 'meros-packages');
        $this->menuPages()->register(ThemeSettingsPage::class, 'meros-theme-settings');
        $this->menuPages()->register(AssetSettingsPage::class, 'meros-assets');
    }

    /**
     * Registers the framework's settings containers.
     *
     * @return void
     */
    private function registerSettingsContainers(): void {
        $this->settingsContainers()->register(FrameworkSettings::class, 'meros_framework_settings');
        $this->settingsContainers()->register(ThemeSettings::class, 'meros_theme_settings');
        $this->settingsContainers()->register(PackageSettings::class, 'meros_package_settings');
        $this->settingsContainers()->register(AssetGroupSettings::class, 'meros_asset_group_settings');
    }

    /**
     * Registers the framework's settings.
     *
     * @return void
     */
    private function registerSettings(): void {
        add_action('meros_packages_registered', function (Collection $packages) {
            $this->registerPackageSettings($packages);
        });
    }

    /**
     * Registers on/off toggle settings for all installed packages.
     *
     * @return void
     */
    private function registerPackageSettings(Collection $packages): void {
        if ($packages->isEmpty()) {
            return;
        }

        $container = $this->settingsContainers('meros_package_settings')
            ?? $this->settingsContainers()->makeFrom('meros_package_settings');

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException("The settings container for the framework must be an instance of SettingsContainer.");
        }

        foreach ($packages as $package) {
            $container->add(function ($setting) use ($package) {
                $setting->boolean($package->getHandle() . '_enabled');
                $setting->label('Enable ' . $package->getName());
                $setting->default(false);
                $setting->field();
                $setting->page('meros-packages');
            });
        }
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
        $this->assetGroups()->register(MerosAdminAssets::class, 'meros_admin_assets', true);
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
