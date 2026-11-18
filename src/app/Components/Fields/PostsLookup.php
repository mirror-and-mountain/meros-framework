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
    
    protected function configure(): void {
        $this->setSerializableProperties(['postType', 'postStatus']);
    }

    protected function lookupQuery() {
        $query = parent::lookupQuery();
        if ($this->postStatus !== 'any') {
            $query->where('post_status', $this->postStatus);
        }

        return $this->postType === 'any'
            ? $query->whereIn('post_type', array_keys(self::getPostTypes()))
            : $query->where('post_type', $this->postType);
    }

    public function resolve(string $search): array {
        if ($this->key !== 'url') {
            return parent::resolve($search);
        }

        $links = [];
        foreach ($this->resolveOptions($search, 'ID') as $id => $label) {
            $url = get_permalink((int) $id);
            if ($url) {
                $links[$url] = $label;
            }
        }

        return $links;
    }

    protected function hydrateDefaultOptions(array $defaultValues): void {
        if ($this->key !== 'url') {
            parent::hydrateDefaultOptions($defaultValues);
            return;
        }

        foreach ($defaultValues as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }

            $id = url_to_postid($url);
            $label = $id ? $this->lookupQuery()->where('ID', $id)->value($this->labelledBy) : null;
            $this->options[$url] = $label ?: $url;
        }
    }

    /**
     * Passing 'any' includes Posts, Pages, and the custom types available in the form builder.
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
     * Adds builder controls specific to the posts lookup field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData The data for the current row in the form builder.
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group,bool $locked = false, array $rowData = []): FieldGroup {
        $postTypes = array_merge([
            'any' => 'Any',
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

        return ['post' => 'Post', 'page' => 'Page'] + $postTypes;
    }
}