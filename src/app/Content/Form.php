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

    protected function configure(): void {
        $this->public();
        $this->hasArchive('forms');
        $this->rewrite(['slug' => 'forms']);
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');
        $this->showInMenu('meros-forms');
        $this->taxonomy(FormTags::class);
        $this->configureFormCapabilities();

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
            if (is_admin() && isset($_GET['post'], $_GET['action']) && $_GET['action'] === 'edit') {
                $postId = intval($_GET['post']);
                $post = get_post($postId);
                if ($post && $post->post_type === $this->getHandle() && $post->post_name === 'f') {
                    wp_redirect(admin_url('edit.php?post_type=' . $this->getHandle()));
                    exit;
                }
            }

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
            'post_title'        => 'Built in: Form for rendering forms that belong to other post types.',
            'post_status'       => 'publish',
            'post_date'         => $time,
            'post_date_gmt'     => $gmtTime,
            'post_modified'     => $time,
            'post_modified_gmt' => $gmtTime
        ]);
    }
}