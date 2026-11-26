<?php

namespace MM\Meros\App\Content;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Content\Concerns\UsesForms;

class FormTemplate extends PostType implements FormHost {
    use UsesForms;

    /**
     * Configures the admin-only, code-provided form templates and their list actions.
     *
     * @return void
     */
    protected function configure(): void {
        $this->private()->showUi();
        $this->handle('form-template');
        $this->label('Form Template', 'Form Templates');
        $this->menuIcon('dashicons-feedback');
        $this->showInMenu('meros-forms');
        $this->taxonomy(FormTags::class);
        $this->supports(['title', 'editor', 'thumbnail'], false);
        $this->configureFormCapabilities(false);
        $this->hasEditableForm()
            ->withActionsEditor()
            ->withFormSettings();

        add_filter('display_post_states', function (array $states, $post): array {
            if ($post->post_type === $this->getHandle()) {
                $states['meros_form_template_provider'] = 'Provided by theme';
            }

            return $states;
        }, 10, 2);

        /**
         * Remove delete and trash actions from bulk actions for this post type
         */
        add_filter('bulk_actions-edit-' . $this->getHandle(), function ($actions) {
            unset( $actions['delete'], $actions['trash'] );
            return $actions;
        });

        /**
         * Remove delete and trash actions from the post row actions for this post type
         */
        add_filter('post_row_actions', function ($actions, $post) {
            if ($post->post_type !== $this->getHandle()) {
                return $actions;
            }

            unset( $actions['trash'], $actions['delete'] );
            return $actions;
        }, 10, 2);

        /**
         * Hide the delete action on the post edit screen for this post type
         */
        add_action( 'admin_head-post.php', function () {
            global $post;
            if ( $post && $post->post_type === $this->getHandle() ) {
                echo '<style>#delete-action { display: none !important; }</style>';
            }
        });
    }
}