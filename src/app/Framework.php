<?php

namespace MM\Meros\App;

use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Components\Orchestrator as ComponentsOrchestrator;
use MM\Meros\App\Assets\Orchestrator as AssetsOrchestrator;
use MM\Meros\App\Admin\Settings\Orchestrator as SettingsOrchestrator;

use MM\Meros\Contracts\Providers\Concerns\IsFrameworkProvider;
use MM\Meros\Contracts\Providers\Concerns\IsNonPackageProvider;

final class Framework extends Provider {
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
            $this->configureMerosEnv();
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
        $this->initialise(ComponentsOrchestrator::class);
        $this->initialise(SettingsOrchestrator::class);
        $this->initialise(AssetsOrchestrator::class);

        $this->registerPostTypes();
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
            // $postType->fields('test-field-group'); // Should resolve the existing one.

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
                $meta->add('object', function ($item) {
                    $item->name('test_meta_repeater');
                    $item->label('Test Meta Repeater');
                    $item->field('repeater', function ($field) {
                        $field->field('text', function ($subfield) {
                            $subfield->name('test_repeater_text');
                            $subfield->label('Test Repeater Text');
                        });

                        $field->field('number', function ($subfield) {
                            $subfield->name('test_repeater_number');
                            $subfield->label('Test Repeater Number');
                        });

                        $field->editForm(function ($form) {
                            $form->field('text', function ($field) {
                                $field->name('test_meta_repeater_text');
                                $field->label('I am a form field');
                                $field->placeholder('Enter some text...');
                            });
                            return $form;
                        });
                    });
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
    // Tables & Migrations
    // =========================================================================

    private function registerTables(): void {
        $this->tables()->register();
    }


    // =========================================================================
    // Local Environment Configuration
    // =========================================================================

    private function configureMerosEnv(): void {
        $this->configureLocalMailTransport();
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
}
