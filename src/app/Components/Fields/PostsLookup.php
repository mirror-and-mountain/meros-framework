<?php

namespace MM\Meros\App\Components\Fields;

use Illuminate\Support\Str;
use MM\Meros\App\Models\Post;
use MM\Meros\Contracts\Features\Components\FieldGroup;

class PostsLookup extends Lookup {
    protected string $model = Post::class;
    protected string $key = 'ID';
    protected string $labelledBy = 'post_title';
    protected string $postType = 'post';
    protected string $postStatus = 'publish';

    /**
     * Override the Lookup contract's form builder setting.
     *
     * @var boolean
     */
    protected static bool $showInFormBuilder = true;

    public static function getBuilderControls(): ?FieldGroup {
        $group = parent::getBuilderControls();

        $postTypes = array_merge([
            'any'  => 'Any',
            'post' => 'Post',
            'page' => 'Page'
        ], self::getPostTypes());

        $group->field('checkbox', [
            'name'  => 'allowsMultiple',
            'label' => 'Allow Multiple Values'
        ]);

        $group->row(function ($row) use ($postTypes) {
            $row->field('select', [
                'name'    => 'postType',
                'label'   => 'Filter by Post Type',
                'options' => $postTypes
            ]);

            $row->field('select', [
                'name'    => 'postStatus',
                'label'   => 'Filter by Post Status',
                'options' => [
                    'publish' => 'Published',
                    'draft'   => 'Draft',
                    'any'     => 'Any'
                ]
            ]);
        });

        return $group;
    }
    
    protected function configure(): void {
        $this->clearFilters();

        if ($this->postStatus !== 'any') {
            $this->filter('post_status', $this->postStatus);
        }

        if ($this->postType !== 'any') {
            $this->filter('post_type', $this->postType);
        }

        $this->setSerializableProperties(['postType', 'postStatus']);
    }

    /**
     * Sets the post type to return options for. Passing 'any' will return results for all post types.
     *
     * @param string $postType
     *
     * @return static
     */
    public function postType(string $postType): static {
        $this->postType = $postType;
        return $this;
    }

    /**
     * Sets the post status to return options for. Passing 'any' will return results for posts with any status.
     *
     * @param string $postStatus
     *
     * @return static
     */
    public function postStatus(string $postStatus): static {
        $this->postStatus = $postStatus;
        return $this;
    }

    /**
     * Internal helper to get a list of post types for the field's configuration in the form builder.
     *
     * @return array
     */
    private static function getPostTypes(): array {
        $postTypes = get_post_types([
            '_builtin' => false
        ]);

        foreach ($postTypes as $handle => $label) {
            $postTypes[$handle] = Str::title(Str::replace(['-', '_'], ' ', $label));
        }

        return $postTypes;
    }
}