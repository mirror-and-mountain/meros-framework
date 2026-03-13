<?php 

namespace MM\Meros\App\Livewire;

use Livewire\Component;
use MM\Meros\Models\Post;
use MM\Meros\Models\User;
use MM\Meros\Models\PostMeta;

class Portal extends Component {
    private array $blocks;
    private User $user;

    public function mount() {
        global $post;
        
        $portal = get_post(10515);

        setup_postdata($portal);
        $post = $portal;

        $userId = get_current_user_id();
        
        if ($userId === 0) {
            // Redirect to login here...
        }

        else {
            $this->user = User::find($userId);

            // Get the theme slug
            $theme = app()->make('meros.theme_manager');
            $themeSlug = $theme->getThemeSlug();

            // We'll get the portal template here...
            $template = get_block_template($themeSlug . '//index');

            if ($template !== null) {
                $this->blocks = parse_blocks($template->content);
                dd($this->blocks);
            } else {
                // 404 here...
            }
        }
    }
    public function render() {
        return view('meros::portal')->layout('meros::portal-layout');
    }
}