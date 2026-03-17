<?php

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Services\Theme\Package;
use MM\Meros\App\Services\Theme\ThemeManager;

trait HasContext {
    /**
     * Indicates whether the item's context was
     * successfully set.
     *
     * @var boolean
     */
    private bool $contextSet = false;

    /**
     * The item's name.
     * 
     * @var string
     */
    protected string $name;

    /**
     * The item's slug.
     * 
     * @var string
     */
    protected string $slug;

    /**
     * The item's prefix. 
     * Used to prefix and settings hooks used by the item.
     * 
     * @var string
     */
    protected string $prefix;

    /**
     * The item's description.
     * 
     * @var string
     */
    protected string $description = '';

    /**
     * The item's directory path.
     * 
     * @var string
     */
    protected string $path;

    /**
     * The item's uri.
     * 
     * @var string
     */
    protected string $uri;

    /**
     * The item author's name.
     *
     * @var string
     */
    protected string $authorName;

    /**
     * The item author's description.
     *
     * @var string
     */
    protected string $authorDesc = '';

    /**
     * The item author's URL.
     *
     * @var string
     */
    protected string $authorUrl = '';

    /**
     * The item author's support URL.
     *
     * @var string
     */
    protected string $authorSupportUrl = '';

    /**
     * The framework directory relative to the theme root.
     * 
     * @var string
     */
    private string $frameworkPath = '';

    /**
     * The framework uri.
     * 
     * @var string
     */
    private string $frameworkUri = '';

    /**
     * Sets context properties.
     * 
     * @return void
     */
    private function setContext(
        string $name = '',
        string $path = '',
        string $uri  = ''
    ): void {

        if ($this instanceof ThemeManager) {
            $theme = wp_get_theme();

            $this->name = $theme->get('Name');
            $this->slug = Str::slug($this->name, '_');
            $this->path = get_stylesheet_directory();
            $this->uri  = get_theme_file_uri();

            $this->prefix = Str::startsWith($this->slug, 'meros')
                ? $this->slug
                : 'meros_' . $this->slug;

            $this->frameworkPath = 'vendor/mirror-and-mountain/meros-framework/src/';
            $this->frameworkUri = trailingslashit($this->uri) . $this->frameworkPath;
        } 
        
        else if ($this instanceof Package) {
            if (!isset($this->authorName)) {
                return;
            }

            if (!isset($this->name)) { 
                $this->name = $name;
            }

            if (!isset($this->prefix)) {
                $this->prefix = Str::slug($this->authorName, '_');
            }
            
            $this->slug = Str::startsWith($this->authorName, 'Meros')
                ? Str::slug($this->name, '_')
                : $this->prefix . '_' . Str::slug($this->name, '_');
        
            $this->path = trailingslashit($path);
            $this->uri  = trailingslashit($uri);
        }

        $this->contextSet = true;
    }

    /**
     * Adds author info to the item
     * 
     * @param array $authorInfo
     */
    final public function addAuthorInfo(array $authorInfo): void {
        $name = $authorInfo['name'] ?? '';
        $desc = $authorInfo['description'] ?? '';
        $url  = $authorInfo['url'] ?? '';
        $supportUrl = $authorInfo['support_url'] ?? '';

        if (!isset($this->authorName)) {
            $this->authorName = $name;
        }

        if ($this->authorDesc === '') {
            $this->authorDesc = $desc;
        }

        if ($this->authorUrl === '') {
            $this->authorUrl = $url;
        }

        if ($this->authorSupportUrl === '') {
            $this->authorSupportUrl = $supportUrl;
        }
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
     * Returns the slug.
     * 
     * @return string
     */
    final public function getSlug(): string {
        return $this->slug;
    }

    /**
     * Returns the uri.
     * 
     * @return string
     */
    final public function getUri(): string {
        return $this->uri;
    }

    /**
     * Returns the theme author's name.
     *
     * @return string
     */
    final public function getAuthorName(): string {
        return $this->authorName;
    }

    /**
     * Returns the theme author's description.
     *
     * @return string
     */
    final public function getAuthorDesc(): string {
        return $this->authorDesc;
    }

    /**
     * Returns the theme author's URL.
     *
     * @return string
     */
    final public function getAuthorUrl(): string {
        return $this->authorUrl;
    }

    /**
     * Returns the theme author's support URL.
     *
     * @return string
     */
    final public function getAuthorSupportUrl(): string {
        return $this->authorSupportUrl;
    }

    /**
     * Returns the framework directory relative to the theme root.
     * 
     * @param bool $full Whether to return with the full path.
     * @return string
     */
    final public function getFrameworkPath(bool $full = true): string {
        return $full 
            ? trailingslashit($this->path) . $this->frameworkPath
            : $this->frameworkPath;
    }

    /**
     * Returns the framework uri.
     * 
     * @return string
     */
    final public function getFrameworkUri(): string {
        return $this->frameworkUri;
    }

    /**
     * Returns the context type
     *
     * @return string|boolean
     */
    final public function getContextType(): string|bool {
        if ($this instanceof ThemeManager) {
            return 'theme';
        } elseif ($this instanceof Package) {
            return 'package';
        } else {
            return false;
        }
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
