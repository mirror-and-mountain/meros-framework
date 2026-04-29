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