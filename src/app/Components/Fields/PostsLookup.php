<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\App\Models\Post;

class PostsLookup extends Lookup {
    protected string $model = Post::class;
    protected string $key = 'ID';
    protected string $labelledBy = 'post_title';
    
    protected function configure(): void {
        $this->filter('post_status', 'publish');
        $this->filter('post_type', 'post');
    }
}