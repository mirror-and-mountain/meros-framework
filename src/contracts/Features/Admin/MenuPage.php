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

class MenuPage extends Feature implements Registrable, Makeable {
    /**
     * The page's title.
     *
     * @var string
     */
    protected string $title = '';

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
     * An array of subpage classes or instances associated with the menu page.
     *
     * @var array<string|MenuPage>
     */
    protected array $subpages = [];

    /**
     * The parent page associated with the submenu page, if any.
     *
     * @var MenuPage|null
     */
    protected ?MenuPage $parentPage = null;

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

    use IsRegistrable, IsMakeable, IsHookable, InstantiatesItems, MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->set('parent_page', $this->passedProps['parent_page'] ?? null);
        $this->set('is_query_page', $this->passedProps['query_page'] ?? false);

        // Ensure subpages are hooked after the parent page.
        $priority = $this->parentPage instanceof self ? 11 : 10;
        $this->setHook('admin_menu', [$this, 'register'], $priority);

        $this->hook();
    }

    final protected function whenConfigured(): void {
        if (!empty($this->subpages)) {
            $this->instantiate('subpages', MenuPage::class, ['parentPage' => $this]);
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
     * Adds a subpage to the menu page. The subpage can be specified as a class name, an instance of MenuPage, or a closure that configures a new MenuPage instance.
     *
     * @param MenuPage|Closure|string $subpageOrClosure
     * @param array                   $callbackOrProps
     * @param array                   $props
     *
     * @return MenuPage The added subpage instance.
     */
    final public function subpage(
        MenuPage|Closure|string $subpageOrClosure,
        Closure|array           $callbackOrProps = [],
        array                   $props = []
    ): MenuPage {
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
                MenuPage::class,
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

            $subpage = $this->makeItem(MenuPage::class, $closure, $props);
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
     * @param MenuPage $parent
     *
     * @return static
     */
    final public function parent(MenuPage $parent): static {
        $this->parentPage = $parent;
        return $this;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->slug($identifier);
    }

    /**
     * Sets the slug for the menu page.
     *
     * @param string $slug
     *
     * @return static
     */
    final public function slug(string $slug): static {
        $slug = Str::slug($slug);
        $this->slug = $slug;

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

    // =========================================================================
    // Getters
    // =========================================================================

    final public function getIdentifier(): string {
        return $this->slug;
    }

    /**
     * Checks if the menu page is a standalone page (i.e., it has no parent and no subpages).
     *
     * @return boolean
     */
    final public function isStandalonePage(): bool {
        return !$this->isParentPage() && !$this->isSubPage();
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
     * @return MenuPage|null
     */
    final public function getSubPage(string $slug): ?MenuPage {
        return $this->getSubPages(true)->first(function ($subpage) use ($slug) {
            return $subpage instanceof MenuPage && $subpage->getSlug() === $slug;
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
     * @return MenuPage|null
     */
    final public function getParentPage(): ?MenuPage {
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
     * @return string
     */
    final public function getSlug(): string {
        return $this->slug;
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

            if ($subpage instanceof MenuPage) {
                $this->renderQueryPage($subpage);
                return;
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($this->title) . '</h1>';

        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }

        if ($this->hasSettings()) {
            $this->renderSettings();
        }

        echo '</div>';
    }

    /**
     * Renders the menu page when it is a query subpage (i.e., it has a parent page and is a query page).
     *
     * @param MenuPage $subpage
     *
     * @return void
     */
    protected function renderQueryPage(MenuPage $subpage): void {
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