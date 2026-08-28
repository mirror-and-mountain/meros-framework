<?php 

namespace MM\Meros\Contracts\Features\Admin;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;
use MM\Meros\Contracts\Features\Concerns\MakesItems;

/**
 * Plan to move subpage logic into a separate subpage contract.
 */

class Page extends Feature implements Registrable, Makeable {
    /**
     * The page's title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Whether to show the page title in the admin area. Defaults to true.
     *
     * @var boolean
     */
    protected bool $showTitle = true;

    /**
     * The page's menu title.
     *
     * @var string
     */
    protected string $menuTitle = '';

    /**
     * The page's capability.
     *
     * @var string
     */
    protected string $capability = 'manage_options';

    /**
     * The page's slug.
     *
     * @var string
     */
    protected string $slug = '';

    /**
     * The page's rendering callback.
     *
     * @var Closure|null
     */ 
    protected ?Closure $callback = null;

    /**
     * The page's icon.
     *
     * @var string
     */
    protected string $icon = 'dashicons-admin-generic';

    /**
     * The page's menu position.
     *
     * @var int
     */
    protected int $position = 2;

    /**
     * The area of wp admin where the page will be displayed.
     *
     * @var string
     */
    protected string $area = 'menu';

    /**
     * Whether to automatically show any settings associated with the page, if there are any.
     *
     * @var boolean
     */
    protected bool $showSettings = true;

    /**
     * An array of subpage classes or instances associated with the menu page.
     *
     * @var array<Page>
     */
    private array $subpages = [];

    /**
     * The parent page associated with the submenu page, if any.
     *
     * @var Page|null
     */
    protected ?Page $parentPage = null;

    /**
     * Whether this is a subpage reached via a query parameter (e.g., ?page=parent-page&subpage=subpage) rather than a standard submenu page.
     *
     * @var boolean
     */
    protected bool $isQueryPage = false;

    /**
     * The query parameter used to identify the subpage when it is a query page.
     *
     * @var string
     */
    protected string $queryPageParam = 'subpage';

    /**
     * The option group associated with the page, if any.
     *
     * @var string
     */
    protected string $optionGroup = '';

    /**
     * Whether the parent page should also appear as the first submenu item.
     *
     * @var bool
     */
    protected bool $showInSubmenu = true;

    /**
     * The title applied to the submenu item for the parent page, if it is included in the submenu.
     *
     * @var string
     */
    protected string $submenuTitle = '';

    /**
     * An array of AJAX actions handled by the page. 
     * 
     * Each action should include a string key representing the action name and 
     * a callable value representing the handler function.
     *
     * @var array
     */
    private array $ajaxActions = [];

    use IsRegistrable, IsMakeable, IsHookable, InstantiatesItems, MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->identifier('slug', 'slug');

        $this->set('parent_page', $this->passedProps['parent_page'] ?? null);
        $this->set('is_query_page', $this->passedProps['query_page'] ?? false);

        // Ensure subpages are hooked after the parent page.
        $priority = $this->parentPage instanceof self ? 11 : 10;
        $this->setHook('admin_menu', [$this, 'register'], $priority);

        $this->hook();
    }

    final protected function whenConfigured(): void {
        if (!empty($this->ajaxActions)) {
            $this->registerAjaxActions();
        }
    }

    /**
     * Registers any given AJAX actions with WordPress.
     *
     * @return void
     */
    private function registerAjaxActions(): void {
        foreach ($this->ajaxActions as $action => $handler) {
            if (is_callable($handler)) {
                add_action('wp_ajax_' . $action, $handler);
            }
        }
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * Registers the menu page with WordPress.
     *
     * @return void
     */
    final public function register(): void {
        if (empty($this->title) || empty($this->menuTitle) || empty($this->slug)) {
            return;
        }

        if ($this->isStandalonePage() || $this->isParentPage()) {
            $this->registerParentPage();
        }

        else if ($this->isSubPage()) {
            $this->registerSubPage();
        }
    }

    /**
     * Registers a parent menu page with WordPress.
     *
     * @return void
     */
    private function registerParentPage(): void {
        $areaFunction = $this->resolveAreaFunction();

        if ($areaFunction !== 'add_menu_page') {
            call_user_func(
                $areaFunction,
                $this->title,
                $this->menuTitle,
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->position
            );
        } else {
            call_user_func(
                $areaFunction,
                $this->title,
                $this->menuTitle,
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->icon,
                $this->position
            );

            if ($this->hasSubPages()) {
                // Normalise parent row placement after all submenu registrations complete.
                add_action('admin_menu', function () {
                    $this->normaliseSubmenuPlacement();
                }, 999);
            }
        }
    }

    /**
     * Registers a submenu page with WordPress under its parent page.
     *
     * @return void
     */
    private function registerSubPage(): void {
        add_submenu_page(
            $this->parentPage->getSlug(),
            $this->title,
            $this->menuTitle,
            $this->capability,
            $this->slug,
            [$this, 'render'],
            $this->position
        );
    }

    /**
     * Resolves the appropriate WordPress function for adding a menu page based on the specified area.
     *
     * @return string The name of the WordPress function to call for adding the menu page.
     */
    private function resolveAreaFunction(): string {
        return match ($this->area) {
            'options' => 'add_options_page',
            'tools'   => 'add_management_page',
            'theme'   => 'add_theme_page',
            'users'   => 'add_users_page',
            default   => 'add_menu_page',
        };
    }

    /**
     * Normalises the placement of the parent menu page in the admin menu 
     * to ensure it appears as the first item in its submenu.
     *
     * @return void
     */
    private function normaliseSubmenuPlacement(): void {
        if ($this->showInSubmenu === false && function_exists('remove_submenu_page')) {
            remove_submenu_page($this->slug, $this->slug);
        }

        global $submenu;

        if (!is_array($submenu[$this->slug] ?? null)) {
            return;
        }

        $items       = $submenu[$this->slug];
        $parentIndex = null;

        foreach ($items as $index => $item) {
            if (is_array($item) && ($item[2] ?? null) === $this->slug) {
                $parentIndex = $index;
                break;
            }
        }

        if ($parentIndex === null) {
            return;
        }

        // WordPress submenu labels are stored at index 0.
        $items[$parentIndex][0] = $this->submenuTitle ?: $this->menuTitle;

        if ($parentIndex === 0) {
            $submenu[$this->slug] = array_values($items);
            return;
        }

        $parentItem = $items[$parentIndex];
        unset($items[$parentIndex]);
        array_unshift($items, $parentItem);

        $submenu[$this->slug] = array_values($items);

    }

    // =========================================================================
    // Subpage Management
    // =========================================================================

    /**
     * Adds a subpage to the menu page. The subpage can be specified as a class name, an instance of Page, or a closure that configures a new Page instance.
     *
     * @param Page|Closure|string $subpageOrClosure
     * @param array                   $callbackOrProps
     * @param array                   $props
     *
     * @return Page The added subpage instance.
     */
    final public function subpage(
        Page|Closure|string $subpageOrClosure,
        Closure|array           $callbackOrProps = [],
        array                   $props = []
    ): Page {
        $queryPage = false;

        if ($this->isSubPage() || $this->area !== 'menu') {
            $queryPage = true;
        }
        
        $subpage = null;

        if (is_string($subpageOrClosure)) {
            $classOrAlias = $subpageOrClosure;
            $props = array_merge(
                is_array($callbackOrProps) ? $callbackOrProps : $props,
                ['parent_page' => $this, 'query_page' => $queryPage], 
            );

            $subpage = $this->makeItemFrom(
                $classOrAlias,
                Page::class,
                $callbackOrProps,
                $props
            );
        }

        else if ($subpageOrClosure instanceof Closure) {
            $closure = $subpageOrClosure;

            $props = array_merge(
                is_array($callbackOrProps) ? $callbackOrProps : $props,
                ['parent_page' => $this, 'query_page' => $queryPage],
            );

            $subpage = $this->makeItem(Page::class, $closure, $props);
        }

        else {
            $subpage = $subpageOrClosure;
            $subpage->parent($this);
            $subpage->isQueryPage($queryPage);
        }

        $this->subpages[] = $subpage;
        return $subpage;
    }

    /**
     * Sets the page as a query page, which means it is reached via a query parameter rather than a standard submenu page.
     * 
     * Example: ?page=parent-page&subpage=subpage
     *
     * @param boolean $isQueryPage
     *
     * @return static
     */
    final public function isQueryPage(bool $isQueryPage = true): static {
        $this->isQueryPage = $isQueryPage;
        return $this;
    }

    /**
     * Sets the query parameter used to identify the subpage when it is a query page.
     *
     * @param string $param
     *
     * @return static
     */
    final public function queryPageParam(string $param): static {
        $this->queryPageParam = $param;
        return $this;
    }

    /**
     * Sets the query parameter used to identify the subpage when it is a query page.
     * 
     * Alias for queryPageParam().
     *
     * @param string $param
     *
     * @return static
     */
    final public function subpageParam(string $param): static {
        return $this->queryPageParam($param);
    }

    /**
     * Sets the parent page for the menu page.
     *
     * @param Page $parent
     *
     * @return static
     */
    final public function parent(Page $parent): static {
        $this->parentPage = $parent;
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the slug for the menu page.
     *
     * @param string $slug
     *
     * @return static
     */
    final public function slug(string $slug): static {
        $slug = $this->setIdentifier($slug);

        if (empty($this->title)) {
            $this->title = Str::title(str_replace('-', ' ', $slug));
        }

        if (empty($this->menuTitle)) {
            $this->menuTitle = Str::title(str_replace('-', ' ', $slug));
        }

        return $this;
    }

    /**
     * Sets the title of the menu page.
     *
     * @param string $title
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;

        if (empty($this->menuTitle)) {
            $this->menuTitle = $title;
        }

        if (empty($this->slug)) {
            $this->slug = Str::slug($title);
        }

        return $this;
    }

    /**
     * Sets whether to show the page title in the admin area.
     *
     * @param boolean $show
     *
     * @return static
     */
    final public function showTitle(bool $show = true): static {
        $this->showTitle = $show;
        return $this;
    }

    /**
     * Sets the menu title of the menu page.
     *
     * @param string $menuTitle
     *
     * @return static
     */
    final public function menuTitle(string $menuTitle): static {
        $this->menuTitle = $menuTitle;

        if (empty($this->title)) {
            $this->title = $menuTitle;
        }

        if (empty($this->slug)) {
            $this->slug = Str::slug($menuTitle);
        }

        return $this;
    }

    /**
     * Sets the capability required to access the menu page.
     *
     * @param string $capability
     *
     * @return static
     */
    final public function capability(string $capability): static {
        $this->capability = $capability;
        return $this;
    }

    /**
     * Sets the rendering callback for the menu page.
     *
     * @param Closure $callback
     *
     * @return static
     */
    final public function callback(Closure $callback): static {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Sets the icon URL for the menu page.
     *
     * @param string $icon
     *
     * @return static
     */
    final public function icon(string $icon): static {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Sets the position of the menu page in the admin menu.
     *
     * @param int $position
     *
     * @return static
     */
    final public function position(int $position): static {
        $this->position = $position;
        return $this;
    }

    /**
     * Sets the area of the WordPress admin where the menu page will be displayed.
     *
     * @param string $area
     *
     * @return static
     */
    final public function in(string $area): static {
        $this->area = $area;
        return $this;
    }

    /**
     * Sets the area of the WordPress admin where the menu page will be displayed.
     *
     * @param string $area
     *
     * @return static
     */
    final public function area(string $area): static {
        return $this->in($area);
    }

    /**
     * Hides or shows this parent page submenu item.
     * 
     * Only relevant if this instance is a parent page (i.e., it has subpages and no parent).
     *
     * @param bool $hide
     *
     * @return static
     */
    final public function hideInSubmenu(bool $hide = true): static {
        $this->showInSubmenu = !$hide;
        return $this;
    }

    /**
     * Shows or hides this parent page submenu item.
     * 
     * Only relevant if this instance is a parent page (i.e., it has subpages and no parent).
     *
     * @param bool $show
     *
     * @return static
     */
    final public function showInSubmenu(bool $show = true): static {
        $this->showInSubmenu = $show;
        return $this;
    }

    /**
     * Sets the title for the submenu item of this parent page.
     * 
     * Only relevant if this instance is a parent page (i.e., it has subpages and no parent).
     *
     * @param string $submenuTitle
     *
     * @return static
     */
    final public function showInSubmenuAs(string $submenuTitle): static {
        $this->showInSubmenu = true;
        $this->submenuTitle = $submenuTitle;
        return $this;
    }

    /**
     * Marks the menu page as having associated settings by setting the option group. 
     * 
     * For internal use only.
     *
     * @return static
     */
    final public function __hasSettings(string $optionGroup): static {
        $this->optionGroup = $optionGroup;
        return $this;
    }

    /**
     * Hides any settings associated with the page. This allows for custom rendering if needed.
     *
     * @param boolean $hide
     *
     * @return static
     */
    final public function hideSettings(bool $hide = true): static {
        $this->showSettings = !$hide;
        return $this;
    }

    /**
     * Adds an AJAX action to the menu page. The action will be handled by the provided callable.
     *
     * @param string   $action
     * @param callable $handler
     *
     * @return static
     */
    final public function addAjaxAction(string $action, callable $handler): static {
        if ($this->ajaxActions[$action] ?? null) {
            return $this;
        }

        $this->ajaxActions[$action] = $handler;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Checks if the menu page is a standalone page (i.e., it has no parent and no subpages).
     *
     * @return boolean
     */
    final public function isStandalonePage(): bool {
        return !$this->isParentPage() && !$this->isSubPage() && !$this->isQuerySubPage();
    }

    /**
     * Checks if the menu page is a parent page (i.e., it has subpages and no parent).
     *
     * @return boolean
     */
    final public function isParentPage(): bool {
        return $this->parentPage === null && !empty($this->subpages);
    }

    /**
     * Checks if the menu page has subpages.
     *
     * @return boolean
     */
    final public function hasSubPages(): bool {
        return !empty($this->subpages);
    }

    /**
     * Gets the subpages associated with the menu page.
     *
     * @param boolean $collect Whether to return a Collection or an array.
     *
     * @return Collection|array
     */
    final public function getSubPages(bool $collect = false): Collection|array {
        return $collect ? collect($this->subpages) : $this->subpages;
    }

    /**
     * Gets a specific subpage by its slug.
     *
     * @param string $slug
     *
     * @return Page|null
     */
    final public function getSubPage(string $slug): ?Page {
        return $this->getSubPages(true)->first(function ($subpage) use ($slug) {
            return $subpage instanceof Page && $subpage->getSlug() === $slug;
        });
    }

    /**
     * Checks if the menu page is a subpage (i.e., it has a parent page and is not a query page).
     *
     * @return boolean
     */
    final public function isSubPage(): bool {
        return $this->parentPage !== null && $this->isQueryPage === false;
    }

    /**
     * Checks if the menu page is a query subpage (i.e., it has a parent page and is a query page).
     *
     * @return boolean
     */
    final public function isQuerySubPage(): bool {
        return $this->parentPage !== null && $this->isQueryPage === true;
    }

    /**
     * Gets the parent page of the menu page, if any.
     *
     * @return Page|null
     */
    final public function getParentPage(): ?Page {
        return $this->parentPage;
    }

    /**
     * Checks if the menu page has an associated option group (i.e., it has settings).
     *
     * @return boolean
     */
    final public function hasSettings(): bool {
        return !empty($this->optionGroup);
    }

    /**
     * Gets the slug of the menu page.
     * 
     * @param string $format The format of the slug to return. Can be 'default', 'slug', or 'snake'. Defaults to 'default'.
     *
     * @return string
     */
    final public function getSlug(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Gets the title of the menu page.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    /**
     * Gets the menu title of the menu page.
     *
     * @return string
     */
    final public function getMenuTitle(): string {
        return $this->menuTitle;
    }

    /**
     * Gets the capability required to access the menu page.
     *
     * @return string
     */
    final public function getCapability(): string {
        return $this->capability;
    }

    /**
     * Gets the icon URL of the menu page.
     *
     * @return string
     */
    final public function getIcon(): string {
        return $this->icon;
    }

    /**
     * Gets the position of the menu page in the admin menu.
     *
     * @return int
     */
    final public function getPosition(): int {
        return $this->position;
    }

    /**
     * Returns the page's option group.
     *
     * @return string
     */
    final public function getOptionGroup(): string {
        return $this->optionGroup;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Renders the menu page and invokes the provided callback.
     * May be overridden in subclasses to provide custom rendering logic.
     *
     * @return void
     */
    public function render(): void {
        if ($_GET[$this->queryPageParam] ?? null) {
            $subpage = $this->getSubPages(true)->first(function ($subpage) {
                return $subpage->getSlug() === $_GET[$this->queryPageParam];
            });

            if ($subpage instanceof Page) {
                $this->renderQueryPage($subpage);
                return;
            }
        }

        echo '<div class="wrap">';

        if ($this->showTitle) {
            echo '<h1>' . esc_html($this->title) . '</h1>';
        }

        if (is_callable($this->callback)) {
            call_user_func($this->callback, $this);
        }

        if ($this->hasSettings() && $this->showSettings) {
            $this->renderSettings();
        }

        echo '</div>';
    }

    /**
     * Renders the menu page when it is a query subpage (i.e., it has a parent page and is a query page).
     *
     * @param Page $subpage
     *
     * @return void
     */
    protected function renderQueryPage(Page $subpage): void {
        $subpage->render();
    }

    /**
     * Renders the settings form for the menu page if it has an associated option group.
     *
     * @return void
     */
    protected function renderSettings(): void {
        echo '<form method="post" action="options.php">';
        settings_fields($this->optionGroup);
        do_settings_sections($this->slug);
        submit_button();
        echo '</form>';
    }
}