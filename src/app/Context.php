<?php 

namespace MM\Meros\App;

use Illuminate\Http\Request;

final class Context {
    /**
     * Indicates whether the current context is in WP Admin.
     *
     * @var boolean
     */
    public bool $isAdmin = false;

    /**
     * Indicates whether the current context is on the front-end site.
     *
     * @var boolean
     */
    public bool $isSite  = false;

    /**
     * The current admin screen ID if in admin context, otherwise empty string.
     *
     * @var string
     */
    public string $adminScreen = '';

    /**
     * The current HTTP request instance.
     *
     * @var Request
     */
    protected Request $request;

    /**
     * The current URL
     *
     * @var string
     */
    public string $url = '';

    /**
     * The full URL including query parameters.
     *
     * @var string
     */
    public string $fullUrl = '';

    /**
     * Array of query parameters if applicable.
     *
     * @var array
     */
    public array $params = [];

    /**
     * The path of the current request (excluding the domain).
     *
     * @var string
     */
    public string $path  = '';

    /**
     * The HTTP method of the current request (e.g., GET, POST).
     *
     * @var string
     */
    public string $method = '';

    public function __construct() {
        $this->isAdmin = \is_admin();
        $this->isSite  = ! $this->isAdmin;

        $this->adminScreen = $this->isAdmin ? (isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '') : '';
        
        $this->request = request();
        $this->url     = $this->request->url();
        $this->fullUrl = $this->request->fullUrl();
        $this->path    = $this->request->path();
        $this->method  = $this->request->method();
        $this->params  = $this->request->query();
    }

    /**
     * Adds query arguments to the current URL and returns the modified URL.
     *
     * @param array $args An associative array of query parameters to add (e.g., ['foo' => 'bar']).
     *
     * @return string The modified URL with the added query parameters.
     */
    public function addQueryArgs(array $args): string {
        return add_query_arg($args, $this->url);
    }

    /**
     * Adds query arguments to the current full URL (including existing query parameters) and returns the modified URL.
     *
     * @param array $args An associative array of query parameters to add (e.g., ['foo' => 'bar']).
     *
     * @return string The modified full URL with the added query parameters.
     */
    public function appendQueryArgs(array $args): string {
        return add_query_arg($args, $this->fullUrl);
    }

    /**
     * Determines if the current context is a specific admin screen based on the provided parameters.
     *
     * @param string $page     The page slug to check for (e.g., 'options-general.php').
     * @param string $tab      Optional tab parameter to check for within the page.
     * @param string $provider Optional provider parameter to check for within the page.
     *
     * @return boolean True if the current context matches the specified admin screen, false otherwise.
     */
    public function isFrameworkScreen(string $page, string $tab = '', string $provider = ''): bool {
        if (!$this->isAdmin) {
            return false;
        }

        if ($this->adminScreen !== 'options-general.php') {
            return false;
        }

        if (!isset($this->params['page']) || $this->params['page'] !== $page) {
            return false;
        }

        if ($tab) {
            if (!isset($this->params['tab']) || $this->params['tab'] !== $tab) {
                return false;
            }
        }

        if ($provider) {
            if (!isset($this->params['provider']) || $this->params['provider'] !== $provider) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the current HTTP request object.
     *
     * @return Request
     */
    public function request(): Request {
        return $this->request;
    }

    /**
     * Get the current context instance.
     *
     * @return self
     */
    public function get(): self {
        return $this;
    }
}