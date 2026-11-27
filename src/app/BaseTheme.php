<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;

use MM\Meros\Contracts\Providers\Concerns\IsNonFrameworkProvider;
use MM\Meros\Contracts\Providers\Concerns\IsNonPackageProvider;

use MM\Meros\Contracts\Features\Data\TableCreator;
use MM\Meros\Support\SchemaManager;

use MM\Meros\App\Models\Migration as TrackedMigration;
use MM\Meros\App\Models\Option;

use MM\Meros\App\Components\Fields\Select;

use MM\Meros\Facades\Data\Integrations;

abstract class BaseTheme extends Provider {
    use IsNonFrameworkProvider, IsNonPackageProvider;

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    final protected function init(): void {
        $themeInfo = \wp_get_theme();

        $this->setName($themeInfo->get('Name'));
        $this->setDescription($themeInfo->get('Description'));
        $this->setAuthor($themeInfo->get('Author'));
        $this->setAuthorUrl($themeInfo->get('AuthorURI'));
        $this->setPath(\get_stylesheet_directory());
        $this->setUri(\get_stylesheet_directory_uri());

        // Enable classic menu editor.
        add_theme_support('menus');
        $this->enqueueStyleSheet();
    }

    /**
     * Used here to configure theme settings provided by the framework in addition to any settings already registered by the theme.
     * These settings include options that relate to the theme's behavior on deactivation, such as whether to uninstall all custom tables or reset all settings.
     *
     * @return void
     */
    final public function whenConfigured(): void {
        // Adds mail-related settings to the theme's settings container.
        $this->addMailSettings();
        // Adds framework settings to the theme's settings container.
        $this->addMerosSettings();

        // Fires when the theme is activated, triggering any necessary setup actions.
        add_action('after_switch_theme', function () {
            $this->__whenThemeActivated();
        });

        // Fires when the theme is deactivated, triggering any necessary cleanup actions.
        add_action('switch_theme', function () {
            $this->__whenThemeDeactivated();
        });
    }

    /**
     * Adds mail-related settings to the theme's settings container.
     *
     * @return void
     */
    private function addMailSettings(): void {
        $mailers = Integrations::getMailers(true, true);

        if ($mailers->isNotEmpty()) {
            $options = array_merge(['default' => 'Default'], $mailers->mapWithKeys(function ($mailer) {
                return [$mailer->getName() => $mailer->getLabel()];
            })->toArray());

            $this->settings()->add('string', function (Setting $setting) use ($options) {
                $setting->name('default_admin_mailer');
                $setting->label('Default Admin Mailer');
                $setting->description('Select the mailer for emails addressed to the site\'s administration email address. Failed sends and messages with attachments, named recipients or unsupported headers use the WordPress default mailer. The "Default" option always uses the WordPress default mailer.');
                $setting->default('default');
                $setting->field('select', function (Select $field) use ($options) {
                    $field->options($options);
                });
                $setting->section('meros-mail-section');
            });

            $this->settings()->add('string', function (Setting $setting) use ($options) {
                $setting->name('default_transactional_mailer');
                $setting->label('Default Transactional Mailer');
                $setting->description('Select the mailer for emails not addressed to the site\'s administration email address, such as password resets and account notifications. Failed sends and messages with attachments, named recipients or unsupported headers use the WordPress default mailer. The "Default" option always uses the WordPress default mailer.');
                $setting->default('default');
                $setting->field('select', function (Select $field) use ($options) {
                    $field->options($options);
                });
                $setting->section('meros-mail-section');
            });

            add_action('meros_framework_booted', function () {
                $this->configureMailTransport();
            });
        }
    }

    /**
     * Configures the mail transport for the theme based on the selected mailers.
     *
     * @return void
     */
    private function configureMailTransport(): void {
        add_action('wp_mail_succeeded', function (array $mailData): void {
            Log::channel('meros-mail-logs')->info('PHP mailer send succeeded:', [
                'to' => $mailData['to'],
                'subject' => $mailData['subject'],
            ]);
        });

        $mailers = [];
        foreach (['admin', 'transactional'] as $type) {
            $mailerID = $this->getSettingValue('default_' . $type . '_mailer');

            if (!is_string($mailerID) || $mailerID === '' || $mailerID === 'default') {
                continue;
            }

            try {
                $mailer = Integrations::getMailer($mailerID);
            } catch (\InvalidArgumentException $e) {
                Log::channel('meros-mail-logs')->warning('Unable to configure the ' . $type . ' mailer: ' . $e->getMessage());
                continue;
            }

            if ($mailer instanceof Mailer && $mailer->ready()) {
                $mailers[$type] = $mailer;
            }
        }

        if ($mailers === []) {
            return;
        }

        add_filter('pre_wp_mail', function (mixed $pre, array $atts) use ($mailers): mixed {
            if ($pre !== null || !empty($atts['attachments']) || !empty($atts['embeds'])) {
                return $pre;
            }

            $parseRecipients = static function (array|string $recipients): ?array {
                if ($recipients === '') {
                    return [];
                }
                $addresses = [];
                foreach (is_array($recipients) ? $recipients : explode(',', $recipients) as $recipient) {
                    if (!is_string($recipient) || strpbrk($recipient, "\r\n") !== false) {
                        return null;
                    }
                    $address = trim($recipient);
                    if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                        return null;
                    }
                    $addresses[] = $address;
                }
                return $addresses;
            };

            $to = $parseRecipients($atts['to']);
            $adminEmail = get_option('admin_email');
            if ($to === null || $to === []) {
                return null;
            }

            $type = is_string($adminEmail) && in_array(strtolower($adminEmail), array_map('strtolower', $to), true)
                ? 'admin'
                : 'transactional';
            $mailer = $mailers[$type] ?? null;
            if ($mailer === null || !$mailer->ready()) {
                return null;
            }

            $cc = [];
            $bcc = [];
            $html = false;
            $headers = $atts['headers'] ?? [];
            if (is_string($headers)) {
                $headers = explode("\n", str_replace("\r\n", "\n", $headers));
            }
            foreach ($headers as $header) {
                if (trim($header) === '') {
                    continue;
                }
                if (strpbrk($header, "\r\n") !== false || !str_contains($header, ':')) {
                    return null;
                }
                [$name, $value] = array_map('trim', explode(':', $header, 2));
                switch (strtolower($name)) {
                    case 'cc':
                    case 'bcc':
                        $recipients = $parseRecipients($value);
                        if ($recipients === null) {
                            return null;
                        }
                        if (strtolower($name) === 'cc') {
                            $cc = array_merge($cc, $recipients);
                        } else {
                            $bcc = array_merge($bcc, $recipients);
                        }
                        break;
                    case 'content-type':
                        if (!preg_match('/^text\/(plain|html)(?:;\s*charset="?UTF-8"?)?$/i', $value, $matches)) {
                            return null;
                        }
                        $html = strtolower($matches[1]) === 'html';
                        break;
                    case 'mime-version':
                        if ($value !== '1.0') {
                            return null;
                        }
                        break;
                    default:
                        // The integration cannot preserve arbitrary headers.
                        return null;
                }
            }

            if ($mailer->prefersHTML() && !$html) {
                $atts['message'] = nl2br($atts['message']);
                $html = true;
            }

            try {
                $result = $mailer->sendMail($to, $atts['subject'], $atts['message'], $cc, $bcc, $html) ? true : null;
                if ($result === null) {
                    Log::channel('meros-mail')->error(ucfirst($type) . ' mailer send failed; falling back to WordPress.');
                    return null;
                } else {
                    Log::channel('meros-mail')->info(ucfirst($type) . ' mailer send succeeded:', ['to' => $to, 'subject' => $atts['subject']]);
                    return true;
                }
            } catch (\Throwable $e) {
                Log::channel('meros-mail')->error(ucfirst($type) . ' mailer send failed; falling back to WordPress.', ['exception' => $e]);
                return null;
            }
        }, 10, 2);
    }

    /**
     * Adds Meros framework settings to the theme's settings container, allowing users to 
     * configure certain behaviors of framework.
     *
     * @return void
     */
    private function addMerosSettings(): void {
        // Features
        $this->settings()->add('boolean', function (Setting $setting) {
            $setting->name('meros_forms_enabled');
            $setting->label('Enable Meros Forms');
            $setting->description($this->getMerosSettingDescription('meros_forms_enabled'));
            $setting->default(false);
            $setting->field();
            $setting->section('meros-theme-settings-section');
        });

        // Deactivation preferences
        $this->settings()->add('boolean', function (Setting $setting) {
            $setting->name('meros_reset_settings_on_deactivate');
            $setting->label('Reset All Settings On Theme Deactivation');
            $setting->description($this->getMerosSettingDescription('meros_reset_settings_on_deactivate', 'deactivation'));
            $setting->default(false);
            $setting->field();
            $setting->section('meros-theme-settings-section');
        });

        $this->settings()->add('boolean', function (Setting $setting) {
            $setting->name('meros_uninstall_all_tables_on_deactivate');
            $setting->label('Reset All Customisations On Theme Deactivation');
            $setting->description($this->getMerosSettingDescription('meros_uninstall_all_tables_on_deactivate', 'deactivation'));
            $setting->default(false);
            $setting->field();
            $setting->section('meros-theme-settings-section');
        });
    }

    /**
     * Gets descriptions for specific Meros settings to be shown in the wp-admin ui.
     *
     * @param string $settingName
     * @param string $type
     *
     * @return string
     */
    private function getMerosSettingDescription(string $settingName, string $type = ''): string {
        if ($type === 'deactivation') {
            $message = $settingName === 'meros_uninstall_all_tables_on_deactivate' 
                ? 'uninstall all custom tables registered by the meros framework, the theme, and any meros packages' 
                : 'reset all meros settings stored in the database, including settings for the theme and any meros packages';

            return 'Toggling this setting on will ' . $message . ' when the theme is deactivated. This action will likely lead to data loss, so use with caution and always make sure your site is backed-up.';
        }

        if ($settingName === 'meros_forms_enabled') {
            return 'Toggling this setting on will enable the Meros Forms feature, allowing you to create and manage forms within your theme. <a href="https://docs.merosframework.com/forms" target="_blank">Learn more</a>.';
        }

        return '';
    }

    /**
     * Fires when the theme is deactivated, triggering any necessary cleanup actions.
     *
     * @return void
     */
    final protected function whenThemeDeactivated(): void {
        $removeAllTablesOnDeactivate = $this->getConfigurationValue('meros_uninstall_all_tables_on_deactivate', true);
        $resetAllSettingsOnDeactivate = $this->getConfigurationValue('meros_reset_settings_on_deactivate');

        if ($removeAllTablesOnDeactivate) {
            $this->uninstallAllMerosTables();
        }

        if ($resetAllSettingsOnDeactivate) {
            $this->removeAllMerosSettings();
        }
    }

    /**
     * Removes all Meros settings from the database.
     *
     * @return void
     */
    private function removeAllMerosSettings(): void {
        Option::where('option_name', 'LIKE', 'meros_%')->delete();
    }

    /**
     * Uninstalls all Meros tables from the database.
     *
     * @return void
     */
    private function uninstallAllMerosTables(): void {
        $migrationTableInstalled = SchemaManager::trackingTableExists();

        if ($migrationTableInstalled) {
            $migrations = TrackedMigration::where('type', 'create')->orderBy('id', 'desc')->get();

            $migrations->each(function (TrackedMigration $trackedMigration) {
                $path = $trackedMigration->path;
                if (!Str::startsWith($path, $this->getPath())) {
                    $path = trailingslashit($this->getPath()) . $trackedMigration->path;
                }

                $migration = include($path);

                if ($migration instanceof TableCreator) {
                    $migration->__init();
                    $migration->down($trackedMigration->related_table);
                }
            });
        }
    }

    /**
     * Enqueues the theme's stylesheet
     * 
     * @return void
     */
    private function enqueueStyleSheet(): void {
        $handle  = $this->getHandle() . '_style'; // e.g. meros_style.
        $uri     = get_stylesheet_uri();
        $version = filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css');

        add_action('wp_enqueue_scripts', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });

        add_action('enqueue_block_editor_assets', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });
    }

    // =========================================================================
    // Settings Management
    // =========================================================================
    /**
     * Resolves the settings container for the theme.
     *
     * @param SettingsContainers $register The SettingsContainers register.
     *
     * @return SettingsContainer The settings container for the theme.
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $container = $register->get('meros_theme_settings', null, false) ?? 
            $register->makeFrom('meros_theme_settings');

        $hasRegisteredTables = $this->hasRegisteredTables();

        if ($hasRegisteredTables) {
            $themeSettingsPage = $this->menuPages()->get('meros-theme-settings');

            if ($themeSettingsPage === null) {
                $themeSettingsPage = $this->menuPages()->makeFrom('meros-theme-settings');
            }

            $this->initTableManagementPage($themeSettingsPage);
        }

        return $container;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Adds a Wordpress theme support.
     * 
     * @param  string $support
     * @param  mixed  ...$args
     * 
     * @return void
     */
    final protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }
}
