<?php

namespace MM\Meros\App;

use MM\Meros\Contracts\Provider;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\App\Admin\SettingsContainers\FrameworkSettings;
use MM\Meros\App\Admin\SettingsContainers\PackagesSettings;
use MM\Meros\App\Admin\SettingsContainers\ThemeSettings;

use MM\Meros\Contracts\Providers\Concerns\ProvidesSettingsContainers;
use MM\Meros\Contracts\Providers\Concerns\ProvidesSettings;

use MM\Meros\Registers\SettingsContainers;

use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\EmailTemplate;

final class FrameworkOld extends Provider {

    use ProvidesSettingsContainers, ProvidesSettings;

    // =========================================================================
    // Initialisation
    // =========================================================================

    public function init(): void {
        // Set framework preferences
        $this->setPreference('livewire_namespace', 'MM\\Meros\\App\\Livewire');

        if (getenv('MEROS_ENVIRONMENT') && getenv('MEROS_ENVIRONMENT') === 'true') {
            $this->configureLocalMailTransport();
        }

        // Boot
        $this->configure();
    }

    /**
     * Configures the framework's features, settings and menu pages.
     *
     * @return void
     */
    protected function configure(): void {
        $this->settingsContainers()->register(FrameworkSettings::class, 'meros_framework_settings');
        $this->settingsContainers()->register(ThemeSettings::class, 'meros_theme_settings');
        $this->settingsContainers()->register(PackagesSettings::class, 'meros_packages_settings');

        $this->settings()->add()->boolean('test_boolean_setting')->label('Test Boolean Setting')->description('A test boolean setting for demonstration purposes.')->default(true);
        dd($this->settings()->add()->string('test_setting')->label('Test Setting')->description('A test setting for demonstration purposes.')->default('default_value'));
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
    protected function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        return $register->get('meros_framework_settings') ?? $register->checkout($this)->makeFrom('meros_framework_settings');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns the URI to the framework's image resources.
     * 
     * @param string $path A relative path to an image within the framework's resources/img directory. Optional.
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

    /*****************************************************************************************
     * 
     * The following methods are installing core framework tables when required by providers
     * 
     *****************************************************************************************/

    /**
     * Called by provider installers to ensure the framework's core tables are installed
     * before they undertake any installer operations.
     *
     * @return void
     */
    // public function require(string $service = 'migrations'): void {
    //     if ($service === 'migrations') {
    //         $this->requireMigrationsService();
    //         return;
    //     }

    //     if ($service === 'integrations') {
    //         $this->requireIntegrationsService();
    //         return;
    //     }
    // }

    // /**
    //  * Installs the meros_migrations table if it doesn't exist.
    //  *
    //  * @return void
    //  * @throws \RuntimeException if the meros_migrations table cannot be found in the framework's tables collection.
    //  */
    // private function requireMigrationsService() {
    //     $table = $this->tables()
    //         ->discover()
    //         ->checkout($this)
    //         ->all()
    //         ->where('tableName', 'meros_migrations')
    //         ->first();

    //     if ($table === null) {
    //         throw new \RuntimeException('Meros Framework requires the meros_migrations table to manage updates. No such table was found.');
    //     }

    //     if (!$table->isInstalled()) {
    //         $table->install(Str::ulid());
    //     }
    // }

    // /**
    //  * Installs the meros_form_responses table if it doesn't exist.
    //  *
    //  * @return void
    //  * @throws \RuntimeException if the meros_form_responses table cannot be found in the framework's tables collection.
    //  */
    // private function requireFormsService() {
    //     $this->requireMigrationsService(); // Ensure the migrations service is installed before attempting to install forms tables

    //     $tables = $this->tables()
    //         ->discover()
    //         ->checkout($this)
    //         ->all();

    //     $responsesTable = $tables
    //         ->where('tableName', 'meros_form_responses')
    //         ->first();

    //     if ($responsesTable === null) {
    //         throw new \RuntimeException('Meros Framework requires the meros_form_responses table to manage form responses. No such table was found.');
    //     }

    //     if (!$responsesTable->isInstalled()) {
    //         $responsesTable->install(Str::ulid());
    //     }
    // }

    // /**
    //  * Installs the meros_external_connections table if it doesn't exist.
    //  *
    //  * @return void
    //  * @throws \RuntimeException if the meros_external_connections table cannot be found in the framework's tables collection.
    //  */
    // private function requireIntegrationsService(): void {
    //     $this->requireMigrationsService(); // Ensure the migrations service is installed before attempting to install integration tables

    //     $tables = $this->tables()
    //         ->discover()
    //         ->checkout($this)
    //         ->all();

    //     $connectionsTable = $tables
    //         ->where('tableName', 'meros_external_connections')
    //         ->first();

    //     if ($connectionsTable === null) {
    //         throw new \RuntimeException('Meros Framework requires the meros_external_connections table to manage integrations. No such table was found.');
    //     }

    //     $batchID = Str::ulid();

    //     if (!$connectionsTable->isInstalled()) {
    //         $connectionsTable->install($batchID);
    //     }
    // }

    /***********************************************************************
     * 
     * The following methods are for registering the framework's post types
     * 
     ***********************************************************************/
    
    /**
     * Registers the framework's custom post types.
     *
     * @return void
     */
    private function registerPostTypes(): void {
        // Add wp core post types to the register
        $this->postTypes()->make(function ($postType) {
            $postType->name('post');
            $postType->label('Post');
            $postType->isCore();
        }); // The 'post' post type

        $this->postTypes()->make(function ($postType) {
            $postType->name('page');
            $postType->label('Page');
            $postType->isCore();
        }); // The 'page' post type

        // Register framework post types
        if ($this->coreFeatures['forms'] ?? false) {
            $this->registerFormPostType();
            $this->registerFieldGroupPostType();
            $this->registerEmailTemplatePostType();
        }
    }

    /**
     * Registers the form post type.
     *
     * @return void
     */
    private function registerFormPostType(): void {
        // Register the Form post type.
        $this->postTypes()->make(function ($postType) {
            $postType->name('meros-form');
            $postType->label('Form');
            $postType->description('A custom post type for managing Forms.');
            $postType->supports(['title']);
            $postType->useBlocks();
            $postType->menuIcon('dashicons-feedback');
            $postType->public();
            $postType->rewrite(['slug' => 'forms']);

            $postType->meta()->add(function ($meta) {
                $meta->string('schema')
                    ->label('Form Structure')
                    ->description('The structure of the form, stored as a JSON string.')
                    ->default(json_encode([
                        'rows'    => [],
                        'actions' => []
                    ]));
            });

            $postType->meta()->add(function ($meta) {
                $meta->string('some_custom_key')
                    ->label('Some Custom Key')
                    ->description('For Testing')
                    ->default('something else');
            });

            $postType->meta()->add(function ($meta) {
                $meta->array('some_array_meta_key')
                    ->label('Some Array Meta Key')
                    ->description('For Testing')
                    ->default(['item1', 'item2', 'item3']);
            });
        });

        // Filter the edit post link
        add_filter('get_edit_post_link', function ($link, $postId, $context) {
            if ($context === 'display' && get_post_type($postId) === 'meros-form') {
                return home_url('toolbox/form-builder/' . $postId);
            }
            return $link;
        }, 10, 3);

        // Redirect the new link
        add_action('admin_init', function () {
            global $pagenow;

            if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'meros-form') {
                wp_safe_redirect(home_url('toolbox/form-builder'));
                exit;
            }
        });

        // Filter the form post type content for rendering
        add_filter('the_content', function ($content) {
            if (is_singular('meros-form') && in_the_loop() && is_main_query()) {
                $post = get_post();

                if (!$post) {
                    return $content;
                }

                $form = Form::find($post->ID);

                if (!$form) {
                    return $content;
                }

                return \Livewire\Livewire::mount('toolbox::forms.form', ['formID' => $post->ID]);
            
            }
            return $content;
        });
    }

    /**
     * Registers the field group post type.
     *
     * @return void
     */
    private function registerFieldGroupPostType(): void {
        // Register the Field Group post type.
        $this->postTypes()->make(function ($postType) {
            $postType->name('meros-field-group');
            $postType->label('Field Group');
            $postType->description('A custom post type for managing Field Groups.');
            $postType->supports(['title']);
            $postType->menuIcon('dashicons-feedback');
            $postType->public();
            $postType->rewrite(['slug' => 'field-groups']);

            $postType->meta()->add(function ($meta) {
                $meta->string('schema')
                    ->label('Field Group Structure')
                    ->description('The structure of the field group, stored as a JSON string.');
            });

            $postType->meta()->add(function ($meta) {
                $meta->string('render_template')
                    ->label('Render Template')
                    ->description('The Blade template used to render this field group when included in forms and settings pages.')
                    ->field();
            });
        });
    }

    /**
     * Registers the email template post type with associated hooks for handling.
     *
     * @return void
     */
    private function registerEmailTemplatePostType(): void {
        // Create block variations for email template building
        add_filter('get_block_type_variations', function ($variations, $blockType) {
            if ($blockType->name === 'core/group') {
                $variations[] = [
                    'name'        => 'email_header',
                    'title'       => 'Email Header',
                    'description' => 'An area to build the header of an email template',
                    'scope'       => ['block'],
                    'isDefault'   => false,
                    'isActive'    => ['className'],
                    'attributes'  => [
                        'className' => 'meros-email-header',
                        'style'     => [
                            'color'   => [
                                'background' => '#f3f4f6',
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                    ],
                ];

                $variations[] = [
                    'name'        => 'email_body',
                    'title'       => 'Email Body',
                    'description' => 'An area to build the body of an email template',
                    'scope'       => ['block'],
                    'isDefault'   => false,
                    'isActive'    => ['className'],
                    'attributes'  => [
                        'className' => 'meros-email-body',
                        'style'     => [
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                    ],
                ];

                $variations[] = [
                    'name'        => 'email_footer',
                    'title'       => 'Email Footer',
                    'description' => 'An area to build the footer of an email template',
                    'scope'       => ['block'],
                    'isDefault'   => false,
                    'isActive'    => ['className'],
                    'attributes'  => [
                        'className' => 'meros-email-footer',
                        'style'     => [
                            'color'   => [
                                'background' => '#f3f4f6',
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                    ],
                ];
            }
            return $variations;
        }, 10, 2);

        // Register the email template post type.
        $this->postTypes()->make(function ($postType) {
            $postType->name('meros-email-template');
            $postType->label('Email Template');
            $postType->description('A custom post type for managing Email Templates.');
            $postType->menuIcon('dashicons-email');
            $postType->public();
            $postType->rewrite(['slug' => 'email-templates']);
            $postType->supports(['title', 'editor']);
            $postType->useBlocks();

            // Set the allowed blocks.
            $postType->allowedBlocks([
                'core/group',
                'core/columns',
                'core/spacer',
                'core/heading', 
                'core/paragraph', 
                'core/image', 
                'core/button'
            ]);

            // Set the template
            $postType->template([
                [
                    'core/group',
                    [
                        'className' => 'meros-email-header',
                        'layout'    => 'constrained',
                        'style'     => [
                            'color'   => [
                                'background' => '#f3f4f6',
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                        'lock'      => [
                            'move'   => true,
                            'remove' => true,
                        ],
                    ],
                    [
                        [
                            'core/heading',
                            [
                                'content' => 'Email Header',
                                'level'   => 2,
                            ],
                        ],
                    ]
                ],
                [
                    'core/group',
                    [
                        'className' => 'meros-email-body',
                        'layout'    => 'constrained',
                        'style'     => [
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                        'lock'      => [
                            'move'   => true,
                            'remove' => true,
                        ],
                    ],
                    [
                        [
                            'core/paragraph',
                            [
                                'content' => 'Email Body'
                            ],
                        ],
                    ]
                ],
                [
                    'core/group',
                    [
                        'className' => 'meros-email-footer',
                        'layout'    => 'constrained',
                        'style'     => [
                            'color'   => [
                                'background' => '#f3f4f6',
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top'    => '24px',
                                    'right'  => '24px',
                                    'bottom' => '24px',
                                    'left'   => '24px',
                                ],
                            ],
                        ],
                        'lock'      => [
                            'move'   => true,
                            'remove' => true,
                        ],
                    ],
                    [
                        [
                            'core/heading',
                            [
                                'content' => 'Email Footer',
                                'level'   => 2,
                            ],
                        ],
                    ]
                ]
            ]);

            // Register meta to store merge tags for this email template
            $postType->meta()->add(function ($meta) {
                $meta->string('merge_tags')
                    ->label('Merge Tags')
                    ->description('The available merge tags for this email template, stored as a JSON string.');
            });
        });

        // Save merge tags as meta
        add_action('save_post_meros-email-template', function ($postId) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (wp_is_post_revision($postId)) {
                return;
            }

            $template = EmailTemplate::find($postId);

            if (!$template) {
                return;
            }

            // Get post content and extract merge tags
            $content = $template->post_content;

            // Merge tags are in the format {{merge_tag}}.
            preg_match_all('/{{(.*?)}}/', $content, $matches);

            $mergeTags = array_values(array_unique($matches[1] ?? []));

            if ($mergeTags !== []) {
                $template->meta()->updateOrCreate(
                    ['meta_key' => '_meros_email_template_meta'],
                    ['meta_value' => json_encode(['merge_tags' => $mergeTags])]
                );
            }
        }, 100);
    }

    /***************************************************************
     * 
     * The following methods are for settings management
     * 
     ***************************************************************/

    /**
     * Returns all settings registered by the framework, theme and its packages.
     *
     * @param bool $refresh     Whether to refresh the setting values from the database. Default is false.
     * @param bool $filterEmpty Whether to filter out empty settings (null, empty array, or empty string). Default is true.
     *
     * @return array An associative array of setting names and their corresponding values.
     */
    public function getAllSettings(bool $refresh = false, bool $filterEmpty = true): array {
        return SettingsAccessor::all()->mapWithKeys(function ($setting) use ($refresh, $filterEmpty) {
            $value = $setting->getValue($refresh);

            if ($filterEmpty && ($value === [] || is_null($value) || $value === '')) {
                return [];
            }

            return [$setting->getName() => $value];
        })->toArray();
    }

    /**
     * Returns the REST controller service.
     *
     * @return RestController
     */
    private function restController(): RestController {
        return app(RestController::class);
    }

    /***************************************************************
     * 
     * The following methods are for Meros API endpoints
     * 
     ***************************************************************/

    /**
     * Registers REST API routes for the framework.
     *
     * @return void
     */
    private function registerRestRoutes(): void {
        $this->restController()->registerRoutes($this);
    }

    /*************************************************************
     * 
     * The following methods are called on theme activation....
     * 
     *************************************************************/

    public function runActivationTasks(): void {
        $this->ensureAppKey();
        $this->ensurePrettyPermalinks();
        $this->clearSessionFiles();
    }

    /**
     * Ensures that an APP_KEY exists in the theme's .env file.
     *
     * @return void
     */
    private function ensureAppKey(): void {
        $envPath = base_path('.env');
        $key     = 'base64:' . base64_encode(random_bytes(32));
        $comment = "# An App Key is required for some Meros functionality. It is automatically generated on theme activation.";

        if (!file_exists($envPath)) {
            $envContent = "{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
            return;
        }

        $envContent = file_get_contents($envPath);

        if (!preg_match('/^APP_KEY=.*$/m', $envContent)) {
            $envContent = rtrim($envContent) . "\n\n{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
        }
    }

    /**
     * Ensures that pretty permalinks are enabled.
     * 
     * @return void
     */
    private function ensurePrettyPermalinks(): void {
        global $wp_rewrite;
        $permalinkStructure = get_option('permalink_structure');
        if (empty($permalinkStructure) || $permalinkStructure === '/') {
            $wp_rewrite->set_permalink_structure('/%postname%/');
            $wp_rewrite->flush_rules();
        }
    }

    /**
     * Clears session files from the theme's storage directory.
     * 
     * @return void
     */
    private function clearSessionFiles(): void {
        $sessionDir = get_theme_file_path('storage/framework/sessions');

        if (is_dir($sessionDir)) {
            $files = glob($sessionDir . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
