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
use MM\Meros\Services\Database\InstallerController;
use MM\Meros\Services\Registers\Integrations as IntegrationsRegister;

use MM\Meros\Facades\Theme as ThemeAccessor;
use MM\Meros\Facades\Packages as PackagesAccessor;
use MM\Meros\Facades\Blocks as BlocksAccessor;
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

        if ($accountsTable === null || $connectionsTable === null) {
            throw new \RuntimeException('Meros Framework requires the meros_integration_accounts and meros_integration_connections tables to manage integrations. One or both of these tables were not found.');
        }

        $batchID = Str::ulid();

        if (!$accountsTable->isInstalled()) {
            $accountsTable->install($batchID);
        }

        if (!$connectionsTable->isInstalled()) {
            $connectionsTable->install($batchID);
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

    /**
     * Determines whether the current request should be limited to opted-in users.
     *
     * @return bool
     */
    private function shouldRestrictPublicUserChoices(): bool {
        return !current_user_can('edit_posts');
    }

    /**
     * Returns whether the given user has opted into public querying.
     *
     * @param int $userId
     * @return bool
     */
    private function isUserPubliclyQueryable(int $userId): bool {
        $value = get_user_meta($userId, $this->getFrameworkUserMetaKey(), true);

        if (!is_array($value)) {
            return false;
        }

        return !empty($value[$this->getPubliclyQueryableUserFlagKey()]);
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
                $hasTables = $package->hasTables();
                
                if ($hasTables) {
                    $titleHTML .= $this->installerController()->renderInstallerHTML($package);
                }

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
        add_action('meros_providers_registered', function () use ($settings) {
            $integrations = $this->resolvedIntegrations();

            $settings->add()->boolean('enable_integrations')
                ->label('Enable Integrations')
                ->description('Enable the Integrations feature to configure and manage integration connections.')
                ->default(false)
                ->field()
                    ->section('meros-features-integrations');

            if (!$this->integrationsFeatureEnabled()) {
                return;
            }

            // First pass: always register all integration enable toggles (blocks/assets style).
            foreach ($integrations as $integration) {
                $integrationHandle = $integration->getHandle();
                $integrationEnabled = $this->integrationEnabled($integrationHandle);

                // Keep enable toggles flat so all integrations remain independently visible/switchable.
                $enabledSetting = $settings->add()->boolean($integration->getHandle() . '_enable')
                    ->label('Enable ' . $integration->getLabel())
                    ->description($integration->getDescription())
                    ->default(false)
                    ->field()
                        ->section('meros-features-integrations');

                if ($integrationEnabled) {
                    $enabledSetting->titleHTML($this->getIntegrationSettingHTML($integration));
                }
            }

            // Second pass: only enabled integrations get configuration fields on their detail page.
            foreach ($integrations as $integration) {
                $integrationHandle = $integration->getHandle();
                $integrationPageSlug = $this->getIntegrationSettingsPageSlug($integrationHandle);

                if (!$this->integrationEnabled($integrationHandle)) {
                    continue;
                }

                $settings->add(function ($setting) use ($integration) {
                    $setting->string($integration->getHandle() . '_base_uri')
                        ->field('text', function ($field) use ($integration) {
                            $field->label('Base URI');
                            $field->default($integration->getBaseUri());
                        })
                        ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                });

                $settings->add(function ($setting) use ($integration) {
                    $setting->string($integration->getHandle() . '_api_version')
                        ->field('text', function ($field) use ($integration) {
                            $field->label('API Version');
                            $field->default($integration->getApiVersion());
                        })
                        ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                });

                $settings->add(function ($setting) use ($integration) {
                    $setting->string($integration->getHandle() . '_connection_label')
                        ->field('text', function ($field) {
                            $field->label('Connection Label');
                            $field->helpText('Optional label used to pick a saved connection for fluent API calls.');
                        })
                        ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                });

                foreach ($integration->getConfigurationFields() as $configurationField) {
                    $settings->add(function ($setting) use ($integration, $configurationField, $integrationPageSlug) {
                        $configurationField->applyTo(
                            $setting,
                            $integration->getHandle() . '_' . $configurationField->getName()
                        );

                        $setting->section($integrationPageSlug);
                    });
                }
            }
        }, 10, 2);
    }

    /**
     * Returns all discovered integrations registered by framework, theme, and packages.
     *
     * @return Collection
     */
    private function resolvedIntegrations(): Collection {
        $providers = collect([$this, ThemeAccessor::get()])
            ->merge(PackagesAccessor::all() ?? [])
            ->filter(fn ($provider) => $provider instanceof FeatureProvider)
            ->values();

        /** @var IntegrationsRegister $integrationsRegister */
        $integrationsRegister = app(IntegrationsRegister::class);
        $integrations = collect([]);

        foreach ($providers as $provider) {
            $integrationsRegister->checkout($provider);
            $integrations = $integrations->merge($integrationsRegister->allResolved());
        }

        return $integrations->unique(fn ($integration) => $integration->getHandle())->values();
    }

    /**
     * Returns whether the global integrations feature is enabled in framework settings.
     *
     * @return bool
     */
    private function integrationsFeatureEnabled(): bool {
        $settings = get_option('meros_framework_settings', []);
        return (bool) ($settings['integrations']['enable_integrations'] ?? false);
    }

    /**
     * Returns whether a specific integration is enabled in framework settings.
     *
     * @param string $integrationHandle
     * @return bool
     */
    private function integrationEnabled(string $integrationHandle): bool {
        if (!$this->integrationsFeatureEnabled()) {
            return false;
        }

        $settings = get_option('meros_framework_settings', []);

        $integrationSettings = $settings['integrations'] ?? [];

        if (!is_array($integrationSettings)) {
            return false;
        }

        // Preferred nested shape: integrations[handle][handle_enable]
        $nested = $integrationSettings[$integrationHandle] ?? null;

        if (is_array($nested) && array_key_exists($integrationHandle . '_enable', $nested)) {
            return (bool) $nested[$integrationHandle . '_enable'];
        }

        // Backward-compatible fallback: integrations[handle_enable]
        return (bool) ($integrationSettings[$integrationHandle . '_enable'] ?? false);
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
        $settings = get_option('meros_framework_settings', []);
        $integrationSettings = $settings['integrations'] ?? [];

        if (!is_array($integrationSettings)) {
            return false;
        }

        foreach ($integrationSettings as $key => $value) {
            if ($key === 'enable_integrations') {
                continue;
            }

            if (is_array($value) && !empty($value[$key . '_enable'])) {
                return true;
            }

            if (is_string($key) && str_ends_with($key, '_enable') && !empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filters framework installer tables so integration tables only appear when at least one integration is enabled.
     *
     * @param Table $table
     * @return bool
     */
    protected function shouldIncludeInstallerTable(Table $table): bool {
        $integrationTables = [
            'meros_integration_accounts',
            'meros_integration_connections',
            'meros_integration_environments',
        ];

        if (in_array($table->getTableName(), $integrationTables, true) && !$this->hasEnabledIntegrations()) {
            return false;
        }

        return true;
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
                            $hasInstallerTables = $hasThemeTables || $hasFrameworkTables;
                            $isManagingTables = $this->isManagingTablesView();
                            $manageTablesUrl = $this->getThemeTablesManagerUrl();
                            $themeTabUrl = $this->getThemeTabUrl();

                            if ($hasInstallerTables) {
                                $frameworkPlan = $this->installerController()->getInstallerPlanData($this);
                                $themePlan = $this->installerController()->getInstallerPlanData($theme);
                                $frameworkUpdateCount = collect($frameworkPlan['update'])->where('change', 'update')->count();
                                $themeUpdateCount = collect($themePlan['update'])->where('change', 'update')->count();
                                $installCount = count($frameworkPlan['install']) + count($themePlan['install']);
                                $updateCount = $frameworkUpdateCount + $themeUpdateCount;
                                $hasAttention = $installCount > 0 || $updateCount > 0;

                                $operation = sanitize_text_field($_GET['operation'] ?? '');
                                $providerType = sanitize_text_field($_GET['providerType'] ?? '');
                                $providerHandle = sanitize_text_field($_GET['provider'] ?? '');

                                if ($operation !== '') {
                                    $providerLabel = $providerType === 'framework'
                                        ? 'Framework'
                                        : ($providerType === 'theme' ? 'Theme' : 'Provider');

                                    if ($providerType === 'theme' && $providerHandle !== '' && $providerHandle !== $theme->getHandle()) {
                                        $providerLabel = 'Provider';
                                    }

                                    if ($providerType === 'framework' && $providerHandle !== '' && $providerHandle !== $this->getHandle()) {
                                        $providerLabel = 'Provider';
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
                                    echo '<h2 style="margin-top: 2em;">Manage Framework & Theme Tables</h2>';
                                    echo '<p class="description">Review and run installer actions for framework and theme tables from one place.</p>';
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
                                    echo '<p>Manage database tables provided by the framework and your active theme.</p>';
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
                            $integrationHandle = sanitize_key($_GET['integration'] ?? '');

                            if ($integrationHandle !== '' && $this->integrationEnabled($integrationHandle)) {
                                $integration = $this->resolvedIntegrations()
                                    ->first(fn ($registeredIntegration) => $registeredIntegration->getHandle() === $integrationHandle);

                                if ($integration !== null) {
                                    $backUrl = add_query_arg([
                                        'page' => 'meros-features',
                                        'tab'  => 'integrations',
                                    ], admin_url('options-general.php'));

                                    echo '<h2>' . esc_html($integration->getLabel()) . ' Configuration</h2>';
                                    echo '<p><a class="button button-secondary button-small" href="' . esc_url($backUrl) . '">Back to Integrations</a></p>';

                                    if ($integration->getAuthType() === 'oauth') {
                                        echo $this->getIntegrationOAuthSetupHTML($integration);
                                    }

                                    settings_fields('meros_framework_settings_container');
                                    do_settings_sections($this->getIntegrationSettingsPageSlug($integrationHandle));
                                    submit_button();
                                    return;
                                }
                            }

                            settings_fields('meros_framework_settings_container');
                            do_settings_sections('meros-features-integrations');
                            submit_button();
                        }
                    ]
                ]
            ]);
        });
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
     * Returns the page slug used for an integration-specific settings screen.
     *
     * @param string $integrationHandle
     * @return string
     */
    private function getIntegrationSettingsPageSlug(string $integrationHandle): string {
        return 'meros-features-integration-' . sanitize_key($integrationHandle);
    }

    /**
     * Generates the integration row actions HTML shown next to the integration toggle.
     *
     * @param object $integration
     * @return string
     */
    private function getIntegrationSettingHTML(object $integration): string {
        $href = add_query_arg([
            'page' => 'meros-features',
            'tab' => 'integrations',
            'integration' => $integration->getHandle(),
        ], admin_url('options-general.php'));

        return '<div class="meros-provider-links"><a href="' . esc_url($href) . '">Configure</a></div>';
    }

    /**
     * Builds an OAuth setup panel with an authorization link for OAuth-based integrations.
     *
     * @param object $integration
     * @return string
     */
    private function getIntegrationOAuthSetupHTML(object $integration): string {
        $handle = $integration->getHandle();
        $settings = get_option('meros_framework_settings', []);
        $integrationSettings = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];

        $prefixed = static function (string $key) use ($integrationSettings, $handle) {
            $nested = $integrationSettings[$handle] ?? null;

            if (is_array($nested) && array_key_exists($handle . '_' . $key, $nested)) {
                return $nested[$handle . '_' . $key];
            }

            return $integrationSettings[$handle . '_' . $key] ?? '';
        };

        $authorizeUrl = trim((string) $prefixed('authorize_url'));
        $clientId = trim((string) $prefixed('client_id'));
        $scopes = trim((string) $prefixed('scopes'));
        $redirectUri = trim((string) $prefixed('redirect_uri'));

        if ($redirectUri === '') {
            $redirectUri = admin_url('options-general.php?page=meros-features&tab=integrations&integration=' . rawurlencode($handle));
        }

        if ($authorizeUrl === '' || $clientId === '') {
            return '<div class="notice notice-warning inline"><p>Set the OAuth Authorize URL and Client ID, then save to generate an authorization link.</p></div>';
        }

        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => wp_create_nonce('meros_integration_oauth_' . $handle),
        ];

        if ($scopes !== '') {
            $query['scope'] = trim(preg_replace('/\s*,\s*/', ' ', $scopes));
        }

        $oauthHref = add_query_arg($query, $authorizeUrl);

        return '<p><a class="button button-primary" href="' . esc_url($oauthHref) . '" target="_blank" rel="noopener noreferrer">Login & Authorize OAuth</a></p>';
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
        /**
         * Registers a REST API route for rendering Blade views. Accepts a view name and an optional 
         * data payload, renders the specified view with the provided data, and returns the rendered HTML. 
         * 
         * Requires the 'edit_posts' capability to access.
         */
        add_action('rest_api_init', function () {
            register_rest_route('meros/v1', '/get-blade-view', [
                'methods'             => [\WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'callback' => function (\WP_REST_Request $request) {
                    $view = sanitize_text_field($request->get_param('view'));
                    $data = $request->get_param('data');
                    
                    $attributes = [];
                    $viewData   = [];

                    if (!$view) {
                        return new \WP_Error('no_view', 'No view specified', ['status' => 400]);
                    }

                    if (is_array($data)) {
                        $attributes = $data;
                    } 
                    
                    elseif (is_string($data) && $data !== '') {
                        $decoded = json_decode(wp_unslash($data), true);

                        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                            return new \WP_Error('invalid_data', 'Invalid data payload', ['status' => 400]);
                        }

                        $attributes = $decoded;
                    }

                    $viewData = [
                        'attributes' => $attributes,
                        'data'       => $attributes,
                    ];

                    foreach ($attributes as $key => $value) {
                        $viewData[$key] = $this->normaliseRestViewData($value);
                    }

                    try {
                        $rendered = view($view, $viewData)->render();
                        return rest_ensure_response(['html' => $rendered]);
                    } catch (\Exception $e) {
                        return new \WP_Error('render_error', 'Error rendering view: ' . $e->getMessage(), ['status' => 500]);
                    }
                }
            ]);

            register_rest_route('meros/v1', '/dynamic-choice-options', [
                'methods' => [\WP_REST_Server::READABLE],
                'permission_callback' => '__return_true',
                'callback' => function (\WP_REST_Request $request) {
                    return $this->handleDynamicChoiceOptionsRequest($request);
                }
            ]);

            /** Serve this endpoint as raw HTML so block editor fetch().text() receives renderable markup. */
            add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
                if ($request->get_route() !== '/meros/v1/get-blade-view') {
                    return $served;
                }

                if (is_wp_error($result)) {
                    return $served;
                }

                $data = $result instanceof \WP_REST_Response ? $result->get_data() : null;

                if (! is_array($data) || ! isset($data['html'])) {
                    return $served;
                }

                $server->send_header('Content-Type', 'text/html; charset=' . get_option('blog_charset'));
                echo $data['html'];

                return true;
            }, 10, 4);
        });
    }

    /**
     * Handles REST requests for dynamically loaded choice field options.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    private function handleDynamicChoiceOptionsRequest(\WP_REST_Request $request): \WP_REST_Response {
        $source = sanitize_key((string) $request->get_param('source'));

        $options = match ($source) {
            'posts' => $this->buildDynamicPostChoiceOptions($request),
            'users' => $this->buildDynamicUserChoiceOptions($request),
            default => [],
        };

        return rest_ensure_response([
            'options' => $options,
        ]);
    }

    /**
     * Builds dynamic choice options from a WP_Query posts lookup.
     *
     * @param \WP_REST_Request $request
     * @return array<int, array{value:string,text:string}>
     */
    private function buildDynamicPostChoiceOptions(\WP_REST_Request $request): array {
        $postType = sanitize_key((string) ($request->get_param('postType') ?: 'post'));
        if ($postType === '' || !post_type_exists($postType)) {
            return [];
        }

        $postStatus = sanitize_key((string) ($request->get_param('postStatus') ?: 'publish'));
        if (!current_user_can('edit_posts')) {
            $postStatus = 'publish';
        }

        $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 20)));
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $selected = $this->normaliseDynamicChoiceSelectedValues($request->get_param('selected'));
        $taxonomy = sanitize_key((string) ($request->get_param('taxonomy') ?: ''));
        $terms = $this->normaliseDynamicChoiceTerms($request->get_param('terms'));

        $queryArgs = [
            'post_type' => $postType,
            'post_status' => $postStatus,
            'posts_per_page' => $limit,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ];

        if ($selected !== [] && $search === '') {
            $queryArgs['post__in'] = $selected;
            $queryArgs['orderby'] = 'post__in';
            $queryArgs['posts_per_page'] = count($selected);
        } elseif ($search !== '') {
            $queryArgs['s'] = $search;
        }

        if ($taxonomy !== '' && taxonomy_exists($taxonomy) && $terms !== []) {
            $allNumericTerms = count(array_filter($terms, fn($term) => ctype_digit((string) $term))) === count($terms);

            $queryArgs['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field' => $allNumericTerms ? 'term_id' : 'slug',
                'terms' => array_map(
                    fn($term) => $allNumericTerms ? (int) $term : sanitize_title((string) $term),
                    $terms
                ),
            ]];
        }

        $query = new \WP_Query($queryArgs);

        return array_map(function ($post) {
            $title = get_the_title($post);

            return [
                'value' => (string) $post->ID,
                'text' => $title !== '' ? html_entity_decode($title, ENT_QUOTES, get_bloginfo('charset')) : '(no title)',
            ];
        }, $query->posts);
    }

    /**
     * Builds dynamic choice options from a WP_User_Query users lookup.
     *
     * @param \WP_REST_Request $request
     * @return array<int, array{value:string,text:string}>
     */
    private function buildDynamicUserChoiceOptions(\WP_REST_Request $request): array {
        $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 20)));
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $selected = $this->normaliseDynamicChoiceSelectedValues($request->get_param('selected'));
        $role = sanitize_key((string) ($request->get_param('userRole') ?: ''));
        $restrictToPublicUsers = $this->shouldRestrictPublicUserChoices();

        $queryArgs = [
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all',
        ];

        if ($restrictToPublicUsers && ($selected === [] || $search !== '')) {
            $queryArgs['number'] = min(100, max($limit * 3, $limit));
        }

        if ($role !== '') {
            $queryArgs['role'] = $role;
        }

        if ($selected !== [] && $search === '') {
            $queryArgs['include'] = $selected;
            $queryArgs['number'] = count($selected);
            unset($queryArgs['orderby'], $queryArgs['order']);
        } elseif ($search !== '') {
            $queryArgs['search'] = '*' . esc_attr($search) . '*';
            $queryArgs['search_columns'] = ['user_login', 'user_nicename', 'display_name', 'user_email'];
        }

        $query = new \WP_User_Query($queryArgs);
        $results = $query->get_results();

        if (!is_array($results)) {
            return [];
        }

        if ($restrictToPublicUsers) {
            $results = array_values(array_filter($results, function ($user) {
                return $user instanceof \WP_User && $this->isUserPubliclyQueryable((int) $user->ID);
            }));

            $results = array_slice($results, 0, $limit);
        }

        return array_map(function ($user) {
            $label = $user->display_name !== '' ? $user->display_name : $user->user_login;

            return [
                'value' => (string) $user->ID,
                'text' => html_entity_decode($label, ENT_QUOTES, get_bloginfo('charset')),
            ];
        }, $results);
    }

    /**
     * Normalises selected dynamic option IDs from REST input.
     *
     * @param mixed $selected
     * @return array<int, int>
     */
    private function normaliseDynamicChoiceSelectedValues(mixed $selected): array {
        if (is_string($selected)) {
            $selected = array_filter(array_map('trim', explode(',', $selected)));
        }

        if (!is_array($selected)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $selected)));
    }

    /**
     * Normalises comma-separated or array term filters for dynamic options.
     *
     * @param mixed $terms
     * @return array<int, string>
     */
    private function normaliseDynamicChoiceTerms(mixed $terms): array {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        }

        if (!is_array($terms)) {
            return [];
        }

        return array_values(array_filter(array_map(fn($term) => trim((string) $term), $terms)));
    }

    /**
     * Normalises REST view payload values for Blade rendering.
     *
     * Associative arrays are converted to objects so views can use property
     * access like $field->label, while list arrays are preserved.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normaliseRestViewData($value) {
        if (! is_array($value)) {
            return $value;
        }

        $normalised = array_map(fn ($item) => $this->normaliseRestViewData($item), $value);
        $isList = $normalised === [] || array_keys($normalised) === range(0, count($normalised) - 1);

        return $isList ? $normalised : (object) $normalised;
    }

    /***************************************************************
     * 
     * The following methods are used for AJAX calls from WP Admin
     * 
     ***************************************************************/
 
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
