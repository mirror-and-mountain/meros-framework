<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\Table;
use MM\Meros\App\Providers\FrameworkServiceProvider;

use MM\Meros\App\Fields\AdminButton;
use MM\Meros\App\Fields\Checkbox;
use MM\Meros\App\Fields\Checkboxes;
use MM\Meros\App\Fields\Color;
use MM\Meros\App\Fields\Date;
use MM\Meros\App\Fields\Email;
use MM\Meros\App\Fields\Hidden;
use MM\Meros\App\Fields\MultiSelect;
use MM\Meros\App\Fields\Number;
use MM\Meros\App\Fields\Radio;
use MM\Meros\App\Fields\Range;
use MM\Meros\App\Fields\Repeater;
use MM\Meros\App\Fields\RichText;
use MM\Meros\App\Fields\Password;
use MM\Meros\App\Fields\AdvancedSelect;
use MM\Meros\App\Fields\Select;
use MM\Meros\App\Fields\Text;
use MM\Meros\App\Fields\Textarea;
use MM\Meros\App\Fields\Tel;
use MM\Meros\App\Fields\Time;
use MM\Meros\App\Fields\Url;

use MM\Meros\App\FieldWrappers\SiteDefault;
use MM\Meros\App\FieldWrappers\AdminSettings;
use MM\Meros\App\FieldWrappers\AdminDefault;

use MM\Meros\App\FormActions\SendEmailWithTemplate;
use MM\Meros\App\Integrations\ExchangeOnline;
use MM\Meros\App\Integrations\Stripe;

use MM\Meros\App\Admin\SettingsSections\Assets;
use MM\Meros\App\Admin\SettingsSections\Blocks;
use MM\Meros\App\Admin\SettingsSections\Integrations;
use MM\Meros\App\Admin\SettingsSections\Forms;
use MM\Meros\App\Admin\SettingsSections\Packages;

use MM\Meros\App\Admin\Templates\SimpleSettingsPage;
use MM\Meros\App\Admin\Templates\TabbedSettingsPage;
use MM\Meros\App\Admin\Templates\MerosFeaturesPage;

use MM\Meros\App\Theme;
use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\EmailTemplate;
use MM\Meros\Services\Contracts\Forms\DynamicChoiceSource;
use MM\Meros\Services\Controllers\InstallerController;
use MM\Meros\Services\Controllers\IntegrationsController;
use MM\Meros\Services\Controllers\RestController;

use MM\Meros\Facades\Theme as ThemeAccessor;
use MM\Meros\Facades\Blocks as BlocksAccessor;
use MM\Meros\Facades\Packages as PackagesAccessor;
use MM\Meros\Facades\AssetGroups as AssetGroupsAccessor;

final class Framework extends FeatureProvider {
    /**
     * Core features provided by the framework. And whether or not they are enabled.
     *
     * @var array
     */
    private array $coreFeatures = [];

    /**
     * Called from the FrameworkServiceProvider on boot
     * 
     * @param FrameworkServiceProvider $serviceProvider Used to ensure only the FrameworkServiceProvider can call this method.
     *
     * @return self
     */
    public function __initialise(FrameworkServiceProvider $serviceProvider): self {
        // Set framework preferences
        $this->setPreference('livewire_namespace', 'MM\\Meros\\App\\Livewire');

        if (getenv('MEROS_ENVIRONMENT') && getenv('MEROS_ENVIRONMENT') === 'true') {
            $this->configureLocalMailTransport();
        }

        // Initialise framework features
        $this->initCoreFeatures();

        // Boot
        $this->load();
        $this->configure();

        return $this;
    }

    /**
     * Initialises the framework's core features based on the framework settings.
     *
     * @return void
     */
    private function initCoreFeatures(): void {
        $this->coreFeatures['forms'] = get_option('meros_framework_settings')['forms']['enable_forms'] ?? true;
        $this->coreFeatures['integrations'] = $this->integrationsFeatureEnabled() && $this->hasEnabledIntegrationSettings();
    }

    /**
     * Returns whether the given core feature is enabled.
     *
     * @param string $feature The name of the core feature to check.
     *
     * @return bool
     */
    public function featureEnabled(string $feature): bool {
        return $this->coreFeatures[$feature] ?? false;
    }

    /**
     * Initialises the framework's features
     *
     * @return void
     */
    protected function load(): void {
        $this->registerDynamicChoiceSources();

        // Register REST API routes
        $this->registerRestRoutes();

        // Register the framework's built-in integrations when the global integrations feature is enabled.
        if ($this->integrationsFeatureEnabled()) {
            $this->integrations()->registerMany([
                'exchange_online' => ExchangeOnline::class,
                'stripe'          => Stripe::class,
            ]);
        }

        // Load forms service if the forms feature is enabled
        if ($this->featureEnabled('forms')) {
            // Make sure the forms service is installed before registering form-related features
            $this->requireFormsService();
            // Register framework form actions
            $this->formActions()->register('send_email_with_template', SendEmailWithTemplate::class);
        }

        // Register framework field types

        // Basic Fields
        $this->fields()->register('admin_button', AdminButton::class);
        $this->fields()->register('text', Text::class);
        $this->fields()->register('textarea', Textarea::class);
        $this->fields()->register('email', Email::class);
        $this->fields()->register('tel', Tel::class);
        $this->fields()->register('url', Url::class);
        $this->fields()->register('number', Number::class);
        $this->fields()->register('range', Range::class);
        $this->fields()->register('checkbox', Checkbox::class);
        $this->fields()->register('color', Color::class);

        // Choice Fields
        $this->fields()->register('select', Select::class);
        $this->fields()->register('multi_select', MultiSelect::class);
        $this->fields()->register('advanced_select', AdvancedSelect::class);
        $this->fields()->register('radio', Radio::class);
        $this->fields()->register('checkboxes', Checkboxes::class);

        // Date Time Fields
        $this->fields()->register('date', Date::class);
        $this->fields()->register('time', Time::class);

        // Special Fields
        $this->fields()->register('hidden', Hidden::class);
        $this->fields()->register('repeater', Repeater::class);
        $this->fields()->register('rich_text', RichText::class);
        $this->fields()->register('password', Password::class);

        // Register framework field wrappers
        $this->fieldWrappers()->register('site_default', SiteDefault::class);

        // Register the Settings field wrapper for admin settings pages
        $this->fieldWrappers()->register('admin_default', AdminDefault::class);
        $this->fieldWrappers()->register('admin_settings', AdminSettings::class);
        

        // Register framework settings sections
        $this->settingsSections()->register('meros-features-packages', Packages::class);
        $this->settingsSections()->register('meros-features-forms', Forms::class);
        $this->settingsSections()->register('meros-features-blocks', Blocks::class);
        $this->settingsSections()->register('meros-features-assets', Assets::class);
        $this->settingsSections()->register('meros-features-integrations', Integrations::class);

        // Register menu page templates
        $this->menuPageTemplates()->register('simple-settings', SimpleSettingsPage::class);
        $this->menuPageTemplates()->register('tabbed-settings', TabbedSettingsPage::class);
        $this->menuPageTemplates()->register('meros-features', MerosFeaturesPage::class);

        // Initialise AJAX handlers for admin interactions
        if ($this->context->isAdmin) {
            $this->initAdminAjaxHandlers();
        }

        $this->integrationsController()->initIntegrationSettingsProtection();
        $this->initIntegrationOAuthHandlers();
    }

    /**
     * Registers built-in dynamic option sources for choice fields.
     *
     * @return void
     */
    private function registerDynamicChoiceSources(): void {
        if ($this->dynamicChoiceSources('posts') === null) {
            $this->dynamicChoiceSources()->make([
                'source' => 'posts',
                'label' => 'Posts',
                'description' => 'Query options from WordPress posts.',
                'configFields' => [
                    [
                        'key' => 'postType',
                        'label' => 'Queried Post Type',
                        'type' => 'text',
                        'default' => 'post',
                        'helpText' => 'The post type to query for option values.',
                    ],
                    [
                        'key' => 'postStatus',
                        'label' => 'Queried Post Status',
                        'type' => 'text',
                        'default' => 'publish',
                        'helpText' => 'The post status to query for option values.',
                    ],
                    [
                        'key' => 'taxonomy',
                        'label' => 'Filter Taxonomy',
                        'type' => 'text',
                        'default' => '',
                        'helpText' => 'Optional taxonomy slug used to filter queried posts.',
                    ],
                    [
                        'key' => 'terms',
                        'label' => 'Filter Terms',
                        'type' => 'text',
                        'default' => '',
                        'helpText' => 'Comma-separated term IDs or slugs used with taxonomy filters.',
                    ],
                    [
                        'key' => 'limit',
                        'label' => 'Dynamic Options Limit',
                        'type' => 'number',
                        'default' => 20,
                        'min' => 1,
                        'helpText' => 'Maximum number of option results to load per request.',
                    ],
                ],
                'resolver' => function (\WP_REST_Request $request): array {
                    return $this->restController()->buildDynamicPostChoiceOptions($request);
                },
            ]);
        }

        if ($this->dynamicChoiceSources('users') === null) {
            $this->dynamicChoiceSources()->make([
                'source' => 'users',
                'label' => 'Users',
                'description' => 'Query options from WordPress users.',
                'configFields' => [
                    [
                        'key' => 'userRole',
                        'label' => 'Filter User Role',
                        'type' => 'text',
                        'default' => '',
                        'helpText' => 'Optional role slug used to filter queried users.',
                    ],
                    [
                        'key' => 'limit',
                        'label' => 'Dynamic Options Limit',
                        'type' => 'number',
                        'default' => 20,
                        'min' => 1,
                        'helpText' => 'Maximum number of option results to load per request.',
                    ],
                ],
                'resolver' => function (\WP_REST_Request $request): array {
                    return $this->restController()->buildDynamicUserChoiceOptions($this, $request);
                },
            ]);
        }
    }

    /**
     * Returns a registered dynamic choice source by key.
     *
     * @param string $source
     * @return DynamicChoiceSource|null
     */
    public function dynamicChoiceSource(string $source): ?DynamicChoiceSource {
        $resolved = $this->dynamicChoiceSources($source);

        return $resolved instanceof DynamicChoiceSource ? $resolved : null;
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

    /**
     * Configures the framework's features, settings and menu pages.
     *
     * @return void
     */
    protected function configure(): void {
        // Run theme activation tasks
        add_action('after_switch_theme', [$this, 'runActivationTasks']);

        add_action('meros_providers_registered', function () {
            if ($this->featureEnabled('integrations')) {
                $this->require('integrations');
            }
        }, 20);
        
        $this->configureSettings();

        // Configure settings and menu pages
        if ($this->context->isAdmin) {
            $this->configureMenuPages();
        }

        // Discover assets
        $this->assets()->discover();

        // Discover blocks
        $this->blocks()->discover();

        // Register post types
        $this->registerPostTypes();

        // Register user meta containers used by framework features.
        $this->registerUserMeta();
    }

    /**
     * Returns the framework instance.
     *
     * @return self
     */
    public function get(): self {
        return $this;
    }

    /**
     * Returns the URI to the framework's image resources.
     * 
     * @phan-param string $path A relative path to an image within the framework's resources/img directory. Optional.
     *
     * @return string
     */
    public function img(string $path = ''): string {
        return $this->getUri() . 'resources/img/' . ltrim($path, '/');
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
    public function require(string $service = 'migrations'): void {
        if ($service === 'migrations') {
            $this->requireMigrationsService();
            return;
        }

        if ($service === 'integrations') {
            $this->requireIntegrationsService();
            return;
        }
    }

    /**
     * Installs the meros_migrations table if it doesn't exist.
     *
     * @return void
     * @throws \RuntimeException if the meros_migrations table cannot be found in the framework's tables collection.
     */
    private function requireMigrationsService() {
        $table = $this->tables()
            ->discover()
            ->checkout($this)
            ->all()
            ->where('tableName', 'meros_migrations')
            ->first();

        if ($table === null) {
            throw new \RuntimeException('Meros Framework requires the meros_migrations table to manage updates. No such table was found.');
        }

        if (!$table->isInstalled()) {
            $table->install(Str::ulid());
        }
    }

    /**
     * Installs the meros_form_responses table if it doesn't exist.
     *
     * @return void
     * @throws \RuntimeException if the meros_form_responses table cannot be found in the framework's tables collection.
     */
    private function requireFormsService() {
        $this->requireMigrationsService(); // Ensure the migrations service is installed before attempting to install forms tables

        $tables = $this->tables()
            ->discover()
            ->checkout($this)
            ->all();

        $responsesTable = $tables
            ->where('tableName', 'meros_form_responses')
            ->first();

        if ($responsesTable === null) {
            throw new \RuntimeException('Meros Framework requires the meros_form_responses table to manage form responses. No such table was found.');
        }

        if (!$responsesTable->isInstalled()) {
            $responsesTable->install(Str::ulid());
        }
    }

    /**
     * Installs the meros_integration_accounts and meros_integration_connections tables if they don't exist.
     *
     * @return void
     * @throws \RuntimeException if either the meros_integration_accounts or meros_integration_connections table cannot be found in the framework's tables collection.
     */
    private function requireIntegrationsService(): void {
        $this->requireMigrationsService(); // Ensure the migrations service is installed before attempting to install integration tables

        $tables = $this->tables()
            ->discover()
            ->checkout($this)
            ->all();

        $accountsTable = $tables
            ->where('tableName', 'meros_integration_accounts')
            ->first();

        $connectionsTable = $tables
            ->where('tableName', 'meros_integration_connections')
            ->first();

        $environmentsTable = $tables
            ->where('tableName', 'meros_integration_environments')
            ->first();

        if ($accountsTable === null || $connectionsTable === null || $environmentsTable === null) {
            throw new \RuntimeException('Meros Framework requires the meros_integration_accounts, meros_integration_connections, and meros_integration_environments tables to manage integrations. One or more of these tables were not found.');
        }

        $batchID = Str::ulid();

        if (!$accountsTable->isInstalled()) {
            $accountsTable->install($batchID);
        }

        if (!$connectionsTable->isInstalled()) {
            $connectionsTable->install($batchID);
        }

        if (!$environmentsTable->isInstalled()) {
            $environmentsTable->install($batchID);
        }
    }

    /***********************************************************************
     * 
     * The following methods are for registering the framework's user meta
     * 
     ***********************************************************************/

    /**
     * Registers core user meta used by framework features.
     *
     * @return void
     */
    private function registerUserMeta(): void {
        $container = $this->userMetaContainer();

        $container->label('Meros User Settings')
            ->description('Controls how this user can be surfaced by Meros-powered features.');

        if (collect($container->getSubItems())->firstWhere('name', $this->getPubliclyQueryableUserFlagKey()) === null) {
            $container->add(function ($subMeta) {
                $subMeta->boolean($this->getPubliclyQueryableUserFlagKey())
                    ->label('Publicly Queryable')
                    ->description('Allow this user to appear in public user lookups powered by Meros dynamic options.')
                    ->default(false)
                    ->field('checkbox');
            });
        }
    }

    /**
     * Returns the root meta key used for framework-owned user profile settings.
     *
     * @return string
     */
    private function getFrameworkUserMetaKey(): string {
        return '_' . Str::replace('-', '_', $this->getHandle()) . '_user_meta';
    }

    /**
     * Returns the nested user meta flag used to opt users into public queries.
     *
     * @return string
     */
    private function getPubliclyQueryableUserFlagKey(): string {
        return 'publicly_queryable';
    }

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
     * Sets up core settings provided by the framework.
     *
     * @return void
     */
    private function configureSettings(): void {
        $packageSettings = $this->settings()->add(function ($setting) {
            $setting->object('packages')
                ->label('Packages');
        });

        $blockSettings = $this->settings()->add(function ($setting) {
            $setting->object('blocks')
                ->label('Blocks');
        });

        $assetGroupSettings = $this->settings()->add(function ($setting) {
            $setting->object('asset_groups')
                ->label('Scripts & Styles');
        });

        $formSettings = $this->settings()->add(function ($setting) {
            $setting->object('forms')
                ->label('Forms');
        });

        $integrationSettings = $this->settings()->add(function ($setting) {
            $setting->object('integrations')
                ->label('Integrations');
        });

        $this->configurePackageSettings($packageSettings);
        $this->configureBlocksSettings($blockSettings);
        $this->configureAssetGroupSettings($assetGroupSettings);
        $this->configureFormsSettings($formSettings);
        $this->configureIntegrationSettings($integrationSettings);
    }

    /**
     * Configures settings for discovered packages, allowing them to be enabled/disabled and providing links to their settings pages if applicable.
     *
     * @param Setting $settings The settings object to add package settings to.
     *
     * @return void
     */
    private function configurePackageSettings(Setting $settings): void {
        add_action('meros_providers_registered', function (BaseTheme $theme, Collection $packages) use ($settings) {
            foreach ($packages as $package) {
                $enabledSetting = $settings->add()->boolean($package->getHandle() . '_enable')
                    ->label('Enable ' . $package->getName())
                    ->description($package->getDescription())
                    ->field()
                        ->section('meros-features-packages');

                $titleHTML = $this->getPackageSettingHTML($package);
                $enabledSetting->titleHTML($titleHTML);
            }
        }, 10, 2);
    }

    /**
     * Configures settings for discovered blocks, allowing them to be enabled/disabled.
     *
     * @param Setting $settings The settings object to add block settings to.
     *
     * @return void
     */
    private function configureBlocksSettings(Setting $settings): void {
        add_action('meros_providers_registered', function () use ($settings) {
            $blocks = BlocksAccessor::all();
            
            foreach ($blocks as $block) {
                $switchable = $block->isSwitchable();
                
                if (!$switchable) {
                    continue;
                }

                $parentSettingName = '';
                $parents           = $block->getParents();
                $dependsOn         = [];

                if ($parents !== []) {
                    foreach ($parents as $parentName) {
                        $parent = $blocks->where('name', $parentName)->first();

                        if ($parent && !$parent->isSwitchable()) {
                            continue;
                        }

                        if ($parent && $parent->isSwitchable()) {
                            $parentSettingName = $parent->getName(true) . '_enable';
                            $dependsOn[]       = $parentSettingName;
                        }
                    }
                }

                $enabledByDefault = $block->provider()->getPreference('blocks_are_enabled_by_default');
                $blockSlug        = $block->getName(true);
                $blockTitle       = Str::title(str_replace('_', ' ', $blockSlug));
                $settingName      = $blockSlug . '_enable';

                $hasDependencies  = $dependsOn !== [];
                $isEnabled        = $enabledByDefault;

                if ($hasDependencies) {
                    $block->dependsOn($dependsOn);
                    $block->enabledByDefault($enabledByDefault);
                    $block->setEnabledSetting($settingName);
                    $isEnabled = $block->isEnabled();
                }

                if ($hasDependencies && count($dependsOn) === 1) {
                    continue; // Skip blocks with a single dependency as they will be hidden/shown based on their parent's setting.
                }

                if ($hasDependencies && !$isEnabled) {
                    continue; // Skip blocks where all dependencies are disabled.
                }

                $settings->add()->boolean($settingName)
                    ->label('Enable the "' . $blockTitle . '" Block')
                    ->description($block->getDescription())
                    ->default($enabledByDefault)
                    ->field()
                        ->section('meros-features-blocks');

                $block->enabledByDefault($enabledByDefault);
                $block->setEnabledSetting($settingName);
            }
        }, 10, 2);
    }

    /**
     * Configures settings for discovered asset groups, allowing them to be enabled/disabled.
     *
     * @param Setting $settings The settings object to add asset group settings to.
     *
     * @return void
     */
    private function configureAssetGroupSettings(Setting $settings): void {
        add_action('meros_providers_registered', function () use ($settings) {
            $groups = AssetGroupsAccessor::all();

            foreach ($groups as $group) {
                $switchable = $group->isSwitchable();

                if (!$switchable) {
                    continue;
                }

                $enabledByDefault = $group->provider()->getPreference('asset_groups_are_enabled_by_default');
                $groupSlug        = $group->getName();
                $groupLabel       = $group->getLabel();
                $settingName      = $groupSlug . '_enable';
                $providerName     = $group->provider()->getName();

                if (Str::contains($providerName, ['Meros', 'Framework'])) {
                    $providerName = Str::replace('Framework', '', $providerName);
                }

                $settings->add()->boolean($settingName)
                    ->label('Enable "' . $providerName . ' - ' . $groupLabel . '"')
                    ->description($group->getDescription())
                    ->default($enabledByDefault)
                    ->field()
                        ->section('meros-features-assets');

                $group->enabledByDefault($enabledByDefault);
                $group->setEnabledSetting($settingName);
                $group->queueAssets();
            }
        }, 10, 2);
    }

    /**
     * Configures settings for the Meros Forms feature, allowing it to be enabled/disabled.
     *
     * @param Setting $settings The settings object to add form settings to.
     *
     * @return void
     */
    private function configureFormsSettings(Setting $settings): void {
        $settings->add()->boolean('enable_forms')
            ->label('Enable Forms')
            ->description('Enable the Meros Forms feature, allowing you to create and manage forms.')
            ->default(true)
            ->field()
                ->section('meros-features-forms');
    }

    /**
     * Configures settings for discovered integrations, allowing them to be enabled/disabled and to expose their declared configuration fields.
     *
     * @param Setting $settings The settings object to add integration settings to.
     *
     * @return void
     */
    private function configureIntegrationSettings(Setting $settings): void {
        $this->integrationsController()->configureIntegrationSettings($this, $settings);
    }

    /**
     * Returns whether the global integrations feature is enabled in framework settings.
     *
     * @return bool
     */
    private function integrationsFeatureEnabled(): bool {
        return $this->integrationsController()->integrationsFeatureEnabled();
    }

    /**
     * Returns whether any discovered integration is enabled.
     *
     * @return bool
     */
    private function hasEnabledIntegrations(): bool {
        return $this->featureEnabled('integrations');
    }

    /**
     * Returns whether any integration has been enabled in framework settings.
     *
     * @return bool
     */
    private function hasEnabledIntegrationSettings(): bool {
        return $this->integrationsController()->hasEnabledIntegrationSettings();
    }

    /**
     * Filters framework installer tables so integration tables only appear when at least one integration is enabled.
     *
     * @param Table $table
     * @return bool
     */
    protected function shouldIncludeInstallerTable(Table $table): bool {
        return $this->integrationsController()->shouldIncludeInstallerTable($table, $this->hasEnabledIntegrations());
    }

    /**
     * Configures the framework's menu pages, including the main features page and any subpages.
     *
     * @return void
     */
    private function configureMenuPages(): void {
        $this->menuPages()->make(function ($page) {
            $page->slug('meros-features');
            $page->title('Features');
            $page->menuTitle('Features');
            $page->position(1);
            $page->template('meros-features', [
                'tabs'  => [
                    'theme' => [
                        'label'    => 'Theme',
                        'callback' => function () {
                            echo '<h2>Theme Configuration</h2>';
                            echo '<p>Manage settings and installable features registered by the framework and the active theme.</p>';
                            
                            $theme = ThemeAccessor::get();
                            $hasThemeTables = $theme->hasTables();
                            $hasFrameworkTables = $this->hasTables();
                            $packageInstallers = PackagesAccessor::all()->filter(function ($package) {
                                return $package->hasTables();
                            });
                            $hasPackageTables = $packageInstallers->isNotEmpty();
                            $hasInstallerTables = $hasThemeTables || $hasFrameworkTables || $hasPackageTables;
                            $isManagingTables = $this->isManagingTablesView();
                            $manageTablesUrl = $this->getThemeTablesManagerUrl();
                            $themeTabUrl = $this->getThemeTabUrl();

                            if ($hasInstallerTables) {
                                $frameworkPlan = $this->installerController()->getInstallerPlanData($this);
                                $themePlan = $this->installerController()->getInstallerPlanData($theme);
                                $packagePlans = $packageInstallers->mapWithKeys(function ($package) {
                                    return [$package->getHandle() => $this->installerController()->getInstallerPlanData($package)];
                                });
                                $frameworkUpdateCount = collect($frameworkPlan['update'])->where('change', 'update')->count();
                                $themeUpdateCount = collect($themePlan['update'])->where('change', 'update')->count();
                                $packageInstallCount = $packagePlans->sum(function ($plan) {
                                    return count($plan['install'] ?? []);
                                });
                                $packageUpdateCount = $packagePlans->sum(function ($plan) {
                                    return collect($plan['update'] ?? [])->where('change', 'update')->count();
                                });
                                $installCount = count($frameworkPlan['install']) + count($themePlan['install']) + $packageInstallCount;
                                $updateCount = $frameworkUpdateCount + $themeUpdateCount + $packageUpdateCount;
                                $hasAttention = $installCount > 0 || $updateCount > 0;

                                $operation = sanitize_text_field($_GET['operation'] ?? '');
                                $providerType = sanitize_text_field($_GET['providerType'] ?? '');
                                $providerHandle = sanitize_text_field($_GET['provider'] ?? '');

                                if ($operation !== '') {
                                    $providerLabel = $providerType === 'framework'
                                        ? 'Framework'
                                        : ($providerType === 'theme' ? 'Theme' : ($providerType === 'package' ? 'Package' : 'Provider'));

                                    if ($providerType === 'theme' && $providerHandle !== '' && $providerHandle !== $theme->getHandle()) {
                                        $providerLabel = 'Provider';
                                    }

                                    if ($providerType === 'framework' && $providerHandle !== '' && $providerHandle !== $this->getHandle()) {
                                        $providerLabel = 'Provider';
                                    }

                                    if ($providerType === 'package' && $providerHandle !== '') {
                                        $package = $packageInstallers->first(fn ($item) => $item->getHandle() === $providerHandle);

                                        if ($package !== null) {
                                            $providerLabel = $package->getName();
                                        }
                                    }

                                    $operationLabel = match($operation) {
                                        'installed'   => 'installed',
                                        'updated'     => 'updated',
                                        'rolled-back' => 'rolled back',
                                        'uninstalled' => 'uninstalled',
                                        default       => '',
                                    };

                                    if ($operationLabel !== '') {
                                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($providerLabel . ' tables ' . $operationLabel . ' successfully.') . '</p></div>';
                                    }
                                }

                                if ($isManagingTables) {
                                    echo '<div class="meros-installer-manager-header">';
                                    echo '<h2 style="margin-top: 2em;">Manage Framework, Theme & Package Tables</h2>';
                                    echo '<p class="description">Review and run installer actions for framework, theme and package tables from one place.</p>';
                                    echo '<p><a class="button button-secondary button-small" href="' . esc_url($themeTabUrl) . '">Back to Theme Settings</a></p>';
                                    echo '</div>';

                                    echo '<div class="meros-installer-groups">';

                                    if ($hasFrameworkTables) {
                                        echo '<section class="meros-installer-group meros-installer-group-framework">';
                                        echo '<h2 style="margin-top: 2em;">Framework Installer</h2>';
                                        echo '<p class="description">These are tables bundled with Meros Framework and managed separately from your theme tables.</p>';
                                        echo $this->installerController()->renderInstallerHTML($this, 'framework');
                                        echo '</section>';
                                    }

                                    if ($hasThemeTables) {
                                        echo '<section class="meros-installer-group meros-installer-group-theme">';
                                        echo '<h2 style="margin-top: 2em;">Theme Installer</h2>';
                                        echo '<p class="description">These are tables provided by your active theme.</p>';
                                        echo $this->installerController()->renderInstallerHTML($theme, 'theme');
                                        echo '</section>';
                                    }

                                    if ($hasPackageTables) {
                                        echo '<section class="meros-installer-group meros-installer-group-packages">';
                                        echo '<h2 style="margin-top: 2em;">Package Installers</h2>';
                                        echo '<p class="description">These are tables provided by enabled or installed packages.</p>';

                                        foreach ($packageInstallers as $package) {
                                            echo '<div class="meros-installer-package">';
                                            echo '<h3>' . esc_html($package->getName()) . '</h3>';
                                            echo $this->installerController()->renderInstallerHTML($package, 'package');
                                            echo '</div>';
                                        }

                                        echo '</section>';
                                    }

                                    echo '</div>';
                                    echo $this->installerController()->renderInstallerModalHTML();
                                } else {
                                    $summary = [];

                                    if ($installCount > 0) {
                                        $summary[] = $installCount . ' pending install' . ($installCount === 1 ? '' : 's');
                                    }

                                    if ($updateCount > 0) {
                                        $summary[] = $updateCount . ' pending update' . ($updateCount === 1 ? '' : 's');
                                    }

                                    if ($summary === []) {
                                        $summary[] = 'No pending table installs or updates';
                                    }

                                    echo '<div class="meros-theme-installer-callout' . ($hasAttention ? ' has-attention' : '') . '">';
                                    echo '<div>';
                                    echo '<h3>Table Management</h3>';
                                    echo '<p>Manage database tables provided by the framework, your active theme and installed packages.</p>';
                                    echo '<p>' . esc_html(implode(' · ', $summary)) . '</p>';
                                    echo '</div>';
                                    echo '<a class="button button-small" href="' . esc_url($manageTablesUrl) . '">Manage Tables</a>';
                                    echo '</div>';
                                }
                            }

                            if (!$isManagingTables) {
                                echo '<h2' . ($hasInstallerTables ? ' style="margin-top: 2em;"' : '') . '>Theme Settings</h2>';
                                $themeSettingsGroup = Str::snake($theme->getHandle()) . '_settings_container';
                                $themeSettingsPage = $theme->getSettingsPageSlug();

                                settings_fields($themeSettingsGroup);
                                do_settings_sections($themeSettingsPage);
                                submit_button();
                            }
                        }
                    ],
                    'packages' => [
                        'label'    => 'Packages',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_container');
                            do_settings_sections('meros-features-packages');
                            submit_button();
                        }
                    ],
                    'blocks' => [
                        'label'    => 'Blocks',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_container');
                            do_settings_sections('meros-features-blocks');
                            submit_button();
                        }
                    ],
                    'forms' => [
                        'label'    => 'Forms',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_container');
                            do_settings_sections('meros-features-forms');
                            submit_button();
                        }
                    ],
                    'assets' => [
                        'label'    => 'Scripts & Styles',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_container');
                            do_settings_sections('meros-features-assets');
                            submit_button();
                        }
                    ],
                    'integrations' => [
                        'label'    => 'Integrations',
                        'callback' => function () {
                            $this->integrationsController()->renderIntegrationsTab($this);
                        }
                    ]
                ]
            ]);
        })->in('options');
    }

    /**
     * Generates the HTML for a package's setting on the features page, including action links and status info.
     *
     * @param Package $package
     * @return string
     */
    private function getPackageSettingHTML(Package $package): string {
        $html        = '';
        $enabled     = $package->isEnabled();
        $handle      = $package->getHandle();
        $hasSettings = $package->hasSettings();

        $html .= 
            '<div class="meros-provider-links">
                <a href="' . esc_url($package->getAuthorUri()) . '" target="_blank">Website</a>
                <span> | </span>
                <a href="' . esc_url($package->getAuthorSupportUri()) . '" target="_blank">Support</a>';

        if ($enabled && $hasSettings) {
            $href = admin_url(
                'options-general.php?page=meros-features' 
                . '&provider=' . $handle 
                . '&origin=' . ($_GET['tab'] ?? 'packages')
            );

            $html .=  
                '<span> | </span>
                <a href="' . esc_url($href) .'">Settings</a>
                </div>';
        }

        else {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Returns the integrations controller service.
     *
     * @return IntegrationsController
     */
    private function integrationsController(): IntegrationsController {
        return app(IntegrationsController::class);
    }

    /**
     * Returns the framework installer controller service.
     *
     * @return InstallerController
     */
    private function installerController(): InstallerController {
        return app(InstallerController::class);
    }

    /**
     * Returns the REST controller service.
     *
     * @return RestController
     */
    private function restController(): RestController {
        return app(RestController::class);
    }

    /**
     * Returns whether the current Theme tab request is in installer management mode.
     *
     * @return bool
     */
    private function isManagingTablesView(): bool {
        $tab = sanitize_key($_GET['tab'] ?? '');
        $view = sanitize_key($_GET['installer_view'] ?? '');

        return $tab === 'theme' && $view === 'tables';
    }

    /**
     * Returns the URL for the Theme tab in installer management mode.
     *
     * @return string
     */
    private function getThemeTablesManagerUrl(): string {
        return add_query_arg([
            'page' => 'meros-features',
            'tab' => 'theme',
            'installer_view' => 'tables',
        ], admin_url('options-general.php'));
    }

    /**
     * Returns the URL for the standard Theme tab view.
     *
     * @return string
     */
    private function getThemeTabUrl(): string {
        return add_query_arg([
            'page' => 'meros-features',
            'tab' => 'theme',
        ], admin_url('options-general.php'));
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

    /***************************************************************
     * 
     * The following methods are used for AJAX calls from WP Admin
     * 
     ***************************************************************/

    /**
     * Registers admin-post handlers for integration OAuth actions.
     *
     * @return void
     */
    private function initIntegrationOAuthHandlers(): void {
        $this->integrationsController()->initIntegrationOAuthHandlers();
    }
 
    /**
     * Initialises AJAX handlers registered by the framework.
     *
     * @return void
     */
    private function initAdminAjaxHandlers(): void {
        add_action('wp_ajax_meros_provider_install_operation', [$this, 'handleProviderInstallerTasks']);
    }

    /**
     * Handles AJAX requests for provider installer operations (install, update, rollback, uninstall).
     *
     * @return void
     */
    public function handleProviderInstallerTasks(): void {
        $this->installerController()->handleProviderInstallerTasks($this);
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
