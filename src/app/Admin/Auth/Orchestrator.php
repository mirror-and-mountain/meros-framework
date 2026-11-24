<?php

namespace MM\Meros\App\Admin\Auth;

use MM\Meros\Contracts\Orchestrators\SettingsOrchestrator;
use MM\Meros\Contracts\Features\Admin\Setting;
use Illuminate\Support\Facades\Schema;
use MM\Meros\Services\Security\Mfa;
use MM\Meros\Services\Security\WordPressMfa;
use PragmaRX\Google2FA\Google2FA;

class Orchestrator extends SettingsOrchestrator {
    protected function configure(): void {
        $this->registerSettings();

        $lifetime = 600;

        app()->singleton(Mfa::class, fn ($app) => new Mfa(
            $app['db']->connection(), $app['encrypter'], new Google2FA(), $lifetime, 5, 300
        ));

        if ($this->getSettingValue('meros_security_mfa_enabled') !== true) {
            return;
        }

        $login = new WordPressMfa(
            fn () => app(Mfa::class),
            function (\WP_User $user): bool {
                $roles = $this->getSettingValue('meros_security_mfa_roles');
                return is_array($roles) && array_intersect($user->roles, $roles) !== [];
            },
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $lifetime,
        );

        add_action('login_init', [$login, 'initialize']);
        add_action('login_form_login', [$login, 'prepareLogin']);
        add_action('login_form', [$login, 'fields']);
        add_action('login_footer', [$login, 'navigation']);
        add_action('login_enqueue_scripts', [$login, 'styles']);
        add_action('login_form_meros_mfa', [$login, 'handle']);
        add_action('show_user_profile', [$login, 'profile']);
        add_filter('authenticate', [$login, 'authenticate'], PHP_INT_MAX);

        add_action('admin_notices', function (): void {
            if (!Schema::hasTable('meros_mfa_credentials') || !Schema::hasTable('meros_mfa_challenges')) {
                echo '<div class="notice notice-error"><p>MFA is enabled but its tables are missing. Install meros_mfa_credentials and meros_mfa_challenges using the Meros database table tools. Interactive logins cannot safely check MFA enrollment until these tables are available.</p></div>';
            }
        });
    }

    /**
     * Registers the settings for multi-factor authentication (MFA) in the admin panel.
     *
     * @return void
     */
    private function registerSettings(): void {
        $this->settings()->add('boolean', function (Setting $setting) {
            $setting->name('meros_security_mfa_enabled');
            $setting->label('Enable Multi-Factor Authentication');
            $setting->description('Enables multi-factor authentication for enhanced security.');
            $setting->default(false);
            $setting->field();
            $setting->page('meros-security');
        });

        if ($this->getSettingValue('meros_security_mfa_enabled') !== true) {
            return;
        }

        $this->settings()->add('array', function (Setting $setting) {
            $setting->name('meros_security_mfa_roles');
            $setting->label('Required MFA Roles');
            $setting->description('Users with any selected role must enroll in MFA. All other users may enable MFA from their profile. Enrolled users must verify MFA at interactive WordPress login regardless of role. An empty selection makes enrollment optional for everyone. Existing sessions and non-interactive authentication are unchanged.');
            $setting->default([]);
            $setting->page('meros-security');
            $setting->field('select', [
                'allowMultiple' => true,
                'options' => [
                    'administrator' => 'Administrator',
                    'editor' => 'Editor',
                    'author' => 'Author',
                    'contributor' => 'Contributor',
                    'subscriber' => 'Subscriber',
                ],
            ]);
        });

        add_filter('meros_admin_select_options', function ($options, $name) {
            if ($name === 'meros_framework_settings[meros_security_mfa_roles]') {
                $options = [];
                foreach (\get_editable_roles() as $roleKey => $role) {
                    $options[$roleKey] = $role['name'];
                }
            }
            return $options;
        }, 10, 2);
    }
}