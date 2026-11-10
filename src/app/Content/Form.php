<?php

namespace MM\Meros\App\Content;

use MM\Meros\App\Models\Post;

use MM\Meros\Contracts\Features\Content\PostType;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;

use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Content\Concerns\UsesForms;

class Form extends PostType implements FormHost {
    use UsesForms;

    // =========================================================================
    // Configuration
    // =========================================================================

    protected function configure(): void {
        $this->public();
        $this->useBlockTemplate();
        $this->menuIcon('dashicons-feedback');

        $this->hasFormType('form')->withoutTemplate();

        // Sets the render callback for form post types, filtering 'the content'
        // to render the form using its stored strucure/
        $this->render(function (string $content, int $postId, Post $post): string {
            $structure = $this->getFormStructure($postId);

            if ($structure === null) {
                return $content;
            }

            $rows = $structure['rows'] ?? [];

            if (empty($rows)) {
                return $content;
            }

            $form = $this->getFormInstance($post, $rows, [], is_admin());
            
            if (!($form instanceof FormComponent)) {
                return $content;
            }

            $form = apply_filters("meros_render_form_{$postId}", $form);
            return $form->html();
        });
    }
}