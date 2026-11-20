<?php

namespace MM\Meros\App\Components\Blocks;

use MM\Meros\Contracts\Features\Content\Interfaces\FormHost;
use MM\Meros\Contracts\Features\Components\DynamicBlock;
use MM\Meros\Contracts\Features\Components\Form as FormComponent;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Content\PostTypes;

class FormBlock extends DynamicBlock {

    protected function configure(): void {
        $this->name('form-block');
        $this->title('Form');
        $this->description('A block for displaying a form.');
        $this->icon('feedback');

        $this->attribute('use_current_form_id', [
            'type'    => 'boolean',
            'default' => true,
            'control' => true
        ]);

        $this->attribute('form_id', [
            'type'    => 'integer',
            'control' => [
                'conditions' => ['use_current_form_id' => false],
            ],
        ]);

        $this->attribute('show_title', [
            'type'    => 'boolean',
            'default' => true,
            'control' => true
        ]);

        $this->attribute('show_description', [
            'type'    => 'boolean',
            'default' => true,
            'control' => true
        ]);

        add_filter("meros_render_block_args_{$this->getName()}", function (array $args) {
            $postId = (int) ($args['post_id'] ?? 0);
            $useCurrentForm = isset($args['attributes']['use_current_form_id']) && $args['attributes']['use_current_form_id'] === true;
            $formId = (int) ($args['attributes']['form_id'] ?? 0);

            if ((!is_int($postId) || $postId === 0) &&
                (!is_int($formId) || $formId === 0)
            ) {
                return $args;
            }

            $postId = $useCurrentForm ? $postId : ($formId !== 0 ? $formId : $postId);

            if ($postId === 0) {
                return $args;
            }

            $post = get_post($postId);
            
            if (!($post instanceof \WP_Post)) {
                return $args;
            }

            $postType = $post->post_type;

            if (!is_admin() && $postType === 'form' && $post->post_name === 'f') {
                $postId = $_GET['id'] ?? 0;

                if ($postId === 0 || !is_numeric($postId)) {
                    return $args;
                }

                $postId = (int) $postId;
                $postType = get_post_type($postId);
            }

            $formCPT  = PostTypes::checkout(Framework::get())->get($postType);

            if ($formCPT instanceof FormHost) {
                $formInstance = $formCPT->getFormInstance($postId);

                if ($formInstance instanceof FormComponent) {
                    $args['post_id'] = $postId;
                    $args['form_instance'] = $formInstance;
                    $args['post_type'] = $postType;
                    $args['post_type_instance'] = $formCPT;
                    return $args;
                }
            }

            return $args;
        });

        $this->renderCallback(function (array $args) {
            $postId           = $args['post_id'] ?? 0;
            $formInstance     = $args['form_instance'] ?? null;
            $postType         = $args['post_type'] ?? '';
            $postTypeInstance = $args['post_type_instance'] ?? null;

            if ($postType === 'form') {
                $title = get_the_title($postId);
                $description = get_the_content(null, false, $postId);
            } else {
                $title = $postId !== 0 && $postTypeInstance instanceof FormHost 
                    ? $postTypeInstance->getFormSettings($postId)['form_title'] ?? '' 
                    : '';

                $description = $postId !== 0 && $postTypeInstance instanceof FormHost 
                    ? $postTypeInstance->getFormSettings($postId)['form_description'] ?? '' 
                    : '';
            }
    
            if ($title === '' && 
                $postTypeInstance instanceof FormHost && 
                $postTypeInstance->hasFormTemplate()
            ) {
                $title = $postTypeInstance->getFormTemplateTitle();
            }

            if ($description === '' && 
                $postTypeInstance instanceof FormHost && 
                $postTypeInstance->hasFormTemplate()
            ) {
                $description = $postTypeInstance->getFormTemplateDescription();
            }

            $hideTitle = isset($args['attributes']['show_title']) && $args['attributes']['show_title'] === false;
            $hideDescription = isset($args['attributes']['show_description']) && $args['attributes']['show_description'] === false;

            if (is_admin()) {
                return '<div style="border:1px solid #dcdcde;border-radius:4px;padding:16px;">
                    <p style="margin:0 0 16px;padding:10px 12px;background:#f0f6fc;border-left:4px solid #72aee6;color:#1d2327;">
                        Forms are not fully rendered in the editor. This preview shows where the form will appear on the front end.
                    </p>
                    
                    ' . ($hideTitle === false ? '<h2>' . esc_html($title) . '</h2>' : '') . '
                    ' . ($hideDescription === false && $description !== '' ? '<p>' . esc_html($description) . '</p>' : '') . '
                    
                    <div aria-hidden="true" style="display:grid;gap:14px;">
                        <div>
                            <div style="width:22%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:36px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div>
                            <div style="width:30%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:36px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div>
                            <div style="width:18%;height:10px;margin-bottom:7px;background:#a7aaad;border-radius:2px;"></div>
                            <div style="height:72px;border:1px solid #c3c4c7;border-radius:2px;background:#f6f7f7;"></div>
                        </div>
                        <div style="width:112px;height:36px;border-radius:2px;background:#2271b1;"></div>
                    </div>
                </div>';
            }

            if (!($formInstance instanceof FormComponent)) {
                return '<div><p>Error: Form instance not found.</p></div>';
            }

            if ($hideTitle === false) {
                $formInstance->title($title);
            }

            if ($hideDescription === false && $description !== '') {
                $formInstance->description($description);
            }

            return $formInstance->hideTitle($hideTitle)->hideDescription($hideDescription)->html();
        });
    }
}