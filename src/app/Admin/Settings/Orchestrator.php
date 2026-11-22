<?php

namespace MM\Meros\App\Admin\Settings;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\Pages\MerosThemeSettings as ThemeSettingsPage;
use MM\Meros\App\Admin\Pages\MerosPackages as PackageSettingsPage;
use MM\Meros\App\Admin\Pages\MerosIntegrations as IntegrationsSettingsPage;
use MM\Meros\App\Admin\Pages\MerosAssets as AssetGroupSettingsPage;
use MM\Meros\App\Admin\Pages\MerosBlocksSettings as BlocksSettingsPage;
use MM\Meros\App\Admin\Pages\MerosFormsStyles as FormsSettingsPage;

use MM\Meros\App\Admin\Pages\MerosDev as DevPage;

use MM\Meros\App\Admin\Sections\MerosSettings;
use MM\Meros\App\Admin\Sections\MerosFormsFieldStyles;
use MM\Meros\App\Admin\Sections\MerosFormsProgressStyles;
use MM\Meros\App\Admin\Sections\MerosFormsButtonStyles;
use MM\Meros\App\Admin\Sections\MerosFormsFeedbackStyles;

use MM\Meros\App\Admin\Sections\MerosIntegrationsCrm;
use MM\Meros\App\Admin\Sections\MerosIntegrationsMail;
use MM\Meros\App\Admin\Sections\MerosIntegrationsGeneral;

use MM\Meros\App\Admin\Settings\Containers\MerosFrameworkSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosIntegrationSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosThemeSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosPackageSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosAssetGroupSettings;
use MM\Meros\App\Admin\Settings\Containers\MerosBlocksSettings;

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
        $this->pages(IntegrationsSettingsPage::class)->register();
        $this->pages(AssetGroupSettingsPage::class)->register();
        $this->pages(BlocksSettingsPage::class)->register();
        $this->pages(FormsSettingsPage::class)->register();
        $this->pages(DevPage::class)->make();
    }

    /**
     * Registers the framework's settings sections.
     *
     * @return void
     */
    private function registerSettingsSections(): void {
        $this->settingsSections()->register(MerosSettings::class, 'meros-theme-settings-section');
        $this->settingsSections()->register(MerosFormsFieldStyles::class, 'meros-forms-field-styles-section');
        $this->settingsSections()->register(MerosFormsProgressStyles::class, 'meros-forms-progress-styles-section');
        $this->settingsSections()->register(MerosFormsButtonStyles::class, 'meros-forms-button-styles-section');
        $this->settingsSections()->register(MerosFormsFeedbackStyles::class, 'meros-forms-feedback-styles-section');
        
        $this->settingsSections()->register(MerosIntegrationsCrm::class, 'meros-integrations-crm-section');
        $this->settingsSections()->register(MerosIntegrationsMail::class, 'meros-integrations-mail-section');
        $this->settingsSections()->register(MerosIntegrationsGeneral::class, 'meros-integrations-general-section');
    }

    /**
     * Registers the framework's settings containers.
     *
     * @return void
     */
    private function registerSettingsContainers(): void {
        $this->settingsContainers(MerosFrameworkSettings::class)->register();
        $this->settingsContainers(MerosIntegrationSettings::class)->register();
        $this->settingsContainers(MerosThemeSettings::class)->register(BaseTheme::class);
        $this->settingsContainers(MerosPackageSettings::class)->register();
        $this->settingsContainers(MerosAssetGroupSettings::class)->register();
        $this->settingsContainers(MerosBlocksSettings::class)->register();
    }

    /**
     * Registers the framework's settings.
     *
     * @return void
     */
    private function registerSettings(): void {
        $this->registerFormSettings();

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
     * Registers global form-related settings in the appearance menu.
     *
     * @return void
     */
    private function registerFormSettings(): void {
        // Progress Bar
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_progress_bar_color');
            $setting->label('Forms Progress Bar Color');
            $setting->description('This controls the colour of the progress bar in forms across the site.');
            $setting->default('#2271b1');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-progress-styles-section');
        });

        // Fields
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_bg_color');
            $setting->label('Forms Field Background Color');
            $setting->description('This controls the background colour of form fields across the site.');
            $setting->default('#f9fafb');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_text_color');
            $setting->label('Forms Field Text Color');
            $setting->description('This controls the text colour of form fields across the site.');
            $setting->default('#20242f');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_border_color');
            $setting->label('Forms Field Border Color');
            $setting->description('This controls the border colour of form fields across the site.');
            $setting->default('#c0c4c9');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_focus_border_color');
            $setting->label('Forms Field Focus Border Color');
            $setting->description('This controls the border colour of form fields when focused across the site.');
            $setting->default('#3b4ce2');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_invalid_border_color');
            $setting->label('Forms Field Invalid Border Color');
            $setting->description('This controls the border colour of form fields when invalid across the site.');
            $setting->default('#ff0000');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_field_valid_border_color');
            $setting->label('Forms Field Valid Border Color');
            $setting->description('This controls the border colour of form fields when valid across the site.');
            $setting->default('#008000');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        $this->settings()->add('integer', function (Setting $setting) {
            $setting->name('mforms_field_border_radius');
            $setting->label('Forms Field Border Radius (px)');
            $setting->description('This controls the border radius of form fields across the site.');
            $setting->default(4);
            $setting->field('number');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-field-styles-section');
        });

        // Messages
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_success_text_color');
            $setting->label('Forms Feedback Success Text Color');
            $setting->description('This controls the text colour of success feedback messages across the site.');
            $setting->default('#166534');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_success_border_color');
            $setting->label('Forms Feedback Success Border Color');
            $setting->description('This controls the border colour of success feedback messages across the site.');
            $setting->default('#86efac');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_success_bg_color');
            $setting->label('Forms Feedback Success Background Color');
            $setting->description('This controls the background colour of success feedback messages across the site.');
            $setting->default('#f0fdf4');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_error_text_color');
            $setting->label('Forms Feedback Error Text Color');
            $setting->description('This controls the text colour of error feedback messages across the site.');
            $setting->default('#991b1b');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_error_border_color');
            $setting->label('Forms Feedback Error Border Color');
            $setting->description('This controls the border colour of error feedback messages across the site.');
            $setting->default('#fca5a5');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_feedback_error_bg_color');
            $setting->label('Forms Feedback Error Background Color');
            $setting->description('This controls the background colour of error feedback messages across the site.');
            $setting->default('#fef2f2');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-feedback-styles-section');
        });

        // Buttons
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_bg_color');
            $setting->label('Forms Button Background Color');
            $setting->description('This controls the background colour of form buttons across the site.');
            $setting->default('#2271b1');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_hover_bg_color');
            $setting->label('Forms Button Background Hover Color');
            $setting->description('This controls the background colour of form buttons when hovered across the site.');
            $setting->default('#1a5a8a');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_border_color');
            $setting->label('Forms Button Border Color');
            $setting->description('This controls the border colour of form buttons across the site.');
            $setting->default('#2271b1');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_hover_border_color');
            $setting->label('Forms Button Border Hover Color');
            $setting->description('This controls the border colour of form buttons when hovered across the site.');
            $setting->default('#1a5a8a');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_text_color');
            $setting->label('Forms Button Text Color');
            $setting->description('This controls the text colour of form buttons across the site.');
            $setting->default('#ffffff');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('mforms_button_hover_text_color');
            $setting->label('Forms Button Text Hover Color');
            $setting->description('This controls the text colour of form buttons when hovered across the site.');
            $setting->default('#ffffff');
            $setting->field('color');
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
        });

        $this->settings()->add('integer', function (Setting $setting) {
            $setting->name('mforms_button_border_radius');
            $setting->label('Forms Button Border Radius (px)');
            $setting->description('This controls the border radius of form buttons across the site.');
            $setting->default(4);
            $setting->field();
            $setting->page('meros-forms-styles');
            $setting->section('meros-forms-button-styles-section');
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
            $container->add('boolean', function (Setting $setting) use ($package) {
                $setting->addContext('is_meros_package_setting', true);
                $setting->setProvider($package);
                $setting->name($package->getHandle() . '_enabled');
                $setting->label('Enable ' . $package->getName());
                $setting->default(false);
                $setting->field();
                $setting->onUpdate(function ($value, $oldValue, $itemName, $optionName) use ($package) {
                    if ($value === $oldValue) {
                        return $value;
                    }

                    if ($value === true) {
                        $package->__whenEnabled();
                    } else {
                        $package->__whenDisabled();
                    }

                    return $value;
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