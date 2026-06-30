<?php

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Framework;
use MM\Meros\App\BaseTheme;
use MM\Meros\App\Package;

trait HasIdentity {
    /**
     * Whether the item's identity was successfully set.
     *
     * @var boolean
     */
    protected bool $identitySet = false;

    /**
     * The item's name.
     * 
     * @var string
     */
    public string $name;

    /**
     * The item's handle.
     * 
     * @var string
     */
    public string $handle;

    /**
     * The item's description.
     * 
     * @var string
     */
    public string $description = '';

    /**
     * The item's directory path.
     * 
     * @var string
     */
    public string $path;

    /**
     * The item's uri.
     * 
     * @var string
     */
    public string $uri;

    /**
     * The item author's name.
     *
     * @var string
     */
    public string $author;

    /**
     * The item author's description.
     *
     * @var string
     */
    public string $authorDesc = '';

    /**
     * The item author's URI.
     *
     * @var string
     */
    public string $authorUri = '';

    /**
     * The item author's support URI.
     *
     * @var string
     */
    public string $authorSupportUri = '';

    /**
     * Sets context properties.
     * 
     * @return void
     */
    protected function setIdentity(
        string $name = '',
        string $path = '',
        string $uri  = ''
    ): void {
        // Set theme identity
        if ($this instanceof BaseTheme) {
            $theme = \wp_get_theme();

            $this->name        = $theme->get('Name');
            $this->author      = $theme->get('Author');
            $this->authorUri   = $theme->get('AuthorURI');
            $this->description = $theme->get('Description');
            $this->handle      = Str::snake($this->name);
            $this->path        = \trailingslashit(get_stylesheet_directory());
            $this->uri         = \trailingslashit(\get_stylesheet_directory_uri());
        } 
        
        // Set package identity
        else if ($this instanceof Package) {
            if (!isset($this->author)) {
                return;
            }

            if (!isset($this->name)) { 
                $this->name = $name;
            }
            
            if (!isset($this->handle)) {
                $this->handle = Str::startsWith($this->author, 'Meros')
                    ? Str::snake($this->name)
                    : Str::snake($this->author) . '_' . Str::snake($this->name);
            }
        
            $this->path = \trailingslashit($path);
            $this->uri  = \trailingslashit($uri);
        }

        else if ($this instanceof  Framework) {
            $this->name   = 'Meros Framework';
            $this->handle = 'meros_framework';

            $this->author           = 'Meros';
            $this->authorUri        = 'https://mirrorandmountain.com';
            $this->authorSupportUri = 'https://mirrorandmountain.com/support';

            $this->path = \trailingslashit(dirname(__DIR__, 2));  
            $this->uri  = \trailingslashit(\get_stylesheet_directory_uri() . '/vendor/mirror-and-mountain/meros-framework/src');
        }

        $this->identitySet = true;
    }

    /**
     * Returns the name.
     * 
     * @return string
     */
    final public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the handle.
     * 
     * @return string
     */
    final public function getHandle(): string {
        return $this->handle;
    }

    /**
     * Returns the description.
     * 
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the path.
     * 
     * @param string $subPath Optional sub-path to append to the base path.
     *
     * @return string
     */
    final public function getPath(string $subPath = ''): string {
        return $this->path . ltrim($subPath, '/');
    }

    /**
     * Returns the uri.
     * 
     * @param string $subURI Optional sub-URI to append to the base URI.
     * 
     * @return string
     */
    final public function getUri(string $subURI = ''): string {
        return $this->uri . ltrim($subURI, '/');
    }

    /**
     * Returns the item's author.
     *
     * @param  bool $slug Whether to return the slugified author name.
     * @return string
     */
    final public function getAuthor(bool $slug = false): string {
        return $slug ? Str::slug($this->author, '_') : $this->author;
    }

    /**
     * Returns the item's author description.
     *
     * @return string
     */
    final public function getAuthorDesc(): string {
        return $this->authorDesc;
    }

    /**
     * Returns the item's author URI.
     *
     * @return string
     */
    final public function getAuthorUri(): string {
        return $this->authorUri;
    }

    /**
     * Returns the item's author support URI.
     *
     * @return string
     */
    final public function getAuthorSupportUri(): string {
        return $this->authorSupportUri;
    }

    /**
     * Returns the slug of the settings page for this item.
     *
     * @return string
     */
    final public function getSettingsPageSlug(): string {
        return $this instanceof Theme 
            ? 'meros-features-theme' 
            : 'meros-features-' . Str::kebab($this->getHandle());
    }

    /**
     * Returns the context type
     *
     * @return string
     */
    final public function getIdentityType(): string {
        if ($this instanceof Theme) {
            return 'theme';
        } elseif ($this instanceof Package) {
            return 'package';
        } 
        
        return 'framework';
    }

    /**
     * Returns the theme's namespace.
     *
     * @return string
     */
    final public function getThemeNamespace(): string {
        $themeClass = Config::get('theme.theme_class');
        return Str::beforeLast($themeClass, '\\');
    }
}
