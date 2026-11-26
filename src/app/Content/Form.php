<?php

namespace MM\Meros\App\Content;

use MM\Meros\Contracts\Features\Content\PostType;

use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Content\Concerns\UsesForms;

use MM\Meros\App\Models\Form as FormModel;

use MM\Meros\Facades\Support\Context;
use MM\Meros\Facades\Content\PostTypes;

class Form extends PostType implements FormHost {
    use UsesForms;

    // =========================================================================
    // Configuration
    // =========================================================================

    /**
     * Configures forms and protects the theme-provided form used to render form hosts.
     *
     * @return void
     */
    protected function configure(): void {
        $this->public();
        $this->hasArchive('forms');
        $this->rewrite(['slug' => 'forms']);
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');
        $this->showInMenu('meros-forms');
        $this->taxonomy(FormTags::class);
        $this->configureFormCapabilities();
        $this->capabilities(array_merge($this->getArg('capabilities'), [
            'edit_post'   => 'edit_form',
            'delete_post' => 'delete_form',
        ]));
        add_filter('map_meta_cap', [$this, 'mapFormCapabilities'], 10, 4);
        add_filter('posts_where', [$this, 'excludeFormHostFromAdminList'], 10, 2);
        add_filter('display_post_states', function (array $states, $post): array {
            if ($post->post_type === $this->getHandle() && $post->post_name === 'f') {
                $states['meros_form_host_provider'] = 'Provided by theme (read-only)';
            }

            return $states;
        }, 10, 2);

        $this->hasEditableForm()
            ->withActionsEditor()
            ->withFormSettings();

        /**
         * Modify the document title for the form at slug /forms/f based on the 'id' query parameter.
         */
        add_filter('document_title_parts', function (array $parts): array {
            $post = get_queried_object();

            if ($post instanceof \WP_Post
                && $post->post_type === $this->getHandle()
                && $post->post_name === 'f'
            ) {
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    $formId = intval($_GET['id']);
                    $postType = get_post_type($formId);

                    $postTypeCpt = PostTypes::get($postType);

                    if ($postTypeCpt instanceof FormHost) {
                        $parts['title'] = get_the_title($formId);
                    } else {
                        $parts['title'] = 'Form not found';
                    }

                } else {
                    $parts['title'] = 'Form not found';
                }
            }

            return $parts;
        });

        add_action('meros_framework_booted', function () {
            if (Context::isEditingPostType($this->getHandle())) {
                $this->ensureFormHostForm();

                add_action('post_row_actions', function ($actions, $post) {
                    if ($post->post_type === $this->getHandle() && $post->post_name === 'f') {
                        unset(
                            $actions['edit'],
                            $actions['inline hide-if-no-js'],
                            $actions['trash'], 
                            $actions['delete'], 
                            $actions['view']
                        );
                    }

                    return $actions;
                }, 10, 2);

                add_action( 'admin_head-post.php', function () {
                    global $post;
                    if ( $post && $post->post_type === $this->getHandle() && $post->post_name === 'f' ) {
                        echo '<style>#delete-action { display: none !important; }</style>';
                    }
                });
            }
        });
    }

    /**
     * Excludes the theme-provided form from the main Forms admin list query.
     *
     * @param string $where The existing query conditions.
     * @param \WP_Query $query The posts query being filtered.
     * @return string
     */
    public function excludeFormHostFromAdminList(string $where, \WP_Query $query): string {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== $this->getHandle()) {
            return $where;
        }

        $screen = get_current_screen();
        if ($screen === null || $screen->base !== 'edit' || $screen->post_type !== $this->getHandle()) {
            return $where;
        }

        global $wpdb;

        return $where . $wpdb->prepare(" AND {$wpdb->posts}.post_name <> %s", 'f');
    }

    /**
     * Resolves per-form editing/deletion permissions and denies changes to the theme-provided form.
     *
     * @param array<string> $capabilities The primitive capabilities already required by WordPress.
     * @param string $capability The requested meta capability.
     * @param int $userId The user whose permissions are being checked.
     * @param array $args Arguments containing the target post ID.
     * @return array<string>
     */
    public function mapFormCapabilities(array $capabilities, string $capability, int $userId, array $args): array {
        if (!in_array($capability, ['edit_post', 'delete_post', 'edit_form', 'delete_form'], true)) {
            return $capabilities;
        }

        $post = isset($args[0]) ? get_post($args[0]) : null;
        if ($post === null || $post->post_type !== $this->getHandle()) {
            return in_array($capability, ['edit_form', 'delete_form'], true)
                ? ['do_not_allow']
                : $capabilities;
        }

        if ($post->post_name === 'f') {
            return ['do_not_allow'];
        }

        return array_map(
            fn (string $requiredCapability): string => in_array($requiredCapability, ['edit_form', 'delete_form'], true)
                ? 'edit_forms'
                : $requiredCapability,
            $capabilities
        );
    }

    /**
     * This method ensure that a form at slug /forms/form always exists. This is
     * to ensure FormHost post types always have a means to display their form using the 'form'
     * post type block template.
     *
     * @return void
     */
    private function ensureFormHostForm(): void {
        $form = FormModel::firstWhere('post_type', $this->getHandle())
            ->where('post_name', 'f')
            ->first();

        if ($form instanceof FormModel) {
            $isPublished = $form->post_status === 'publish';
            if (!$isPublished) {
                $form->post_status = 'publish';
                $form->save();
            }

            return;
        }

        $time = current_time('mysql');
        $gmtTime = current_time('mysql', 1);

        FormModel::create([
            'post_type'         => $this->getHandle(),
            'post_name'         => 'f',
            'post_title'        => 'Built In: Form for rendering forms that belong to other post types',
            'post_status'       => 'publish',
            'post_date'         => $time,
            'post_date_gmt'     => $gmtTime,
            'post_modified'     => $time,
            'post_modified_gmt' => $gmtTime
        ]);
    }
}