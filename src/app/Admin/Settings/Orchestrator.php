<?php

namespace MM\Meros\App\Admin\Settings;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\Pages\MerosThemeSettings as ThemeSettingsPage;
use MM\Meros\App\Admin\Pages\MerosPackages as PackageSettingsPage;
use MM\Meros\App\Admin\Pages\MerosAssets as AssetGroupSettingsPage;

use MM\Meros\App\Admin\Sections\MerosSettings;

use MM\Meros\App\Admin\Settings\Containers\MerosFrameworkSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosThemeSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosPackageSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosAssetGroupSettings;

use MM\Meros\Contracts\Orchestrators\SettingsOrchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesSettingsContainers;

use MM\Meros\App\BaseTheme;
use MM\Meros\App\Package;

class Orchestrator extends SettingsOrchestrator {
    use ProvidesSettingsContainers;

    protected function configure(): void {
        $this->registerMenuPages();
        $this->registerSettingsSections();
        $this->registerSettingsContainers();
        $this->registerSettings();
    }

    /**
     * Registers the framework's wp-admin menu pages.
     *
     * @return void
     */
    private function registerMenuPages(): void {
        $this->pages(PackageSettingsPage::class)->register();
        $this->pages(ThemeSettingsPage::class)->register(BaseTheme::class);
        $this->pages(AssetGroupSettingsPage::class)->register();
    }

    /**
     * Registers the framework's settings sections.
     *
     * @return void
     */
    private function registerSettingsSections(): void {
        $this->settingsSections()->register(MerosSettings::class, 'meros-theme-settings-section');
    }

    /**
     * Registers the framework's settings containers.
     *
     * @return void
     */
    private function registerSettingsContainers(): void {
        $this->settingsContainers(MerosFrameworkSettings::class)->register();
        $this->settingsContainers(MerosThemeSettings::class)->register(BaseTheme::class);
        $this->settingsContainers(MerosPackageSettings::class)->register();
        $this->settingsContainers(MerosAssetGroupSettings::class)->register();
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
}