<?php 

namespace MM\Meros\Contracts\Features\Admin;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;

class SettingsSection extends Feature implements Registrable, Makeable {
    /**
     * The section's id.
     *
     * @var string
     */
    protected string $id = '';

    /**
     * The sections title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The section's render callback.
     *
     * @var Closure|null
     */
    protected ?Closure $callback = null;

    /**
     * The slug of the menu page associated with this section
     * 
     * @var string
     */
    protected string $menuPage = '';

    /**
     * An array of additional arguments to for the section.
     *
     * @var array
     */
    protected array $args = [];

    use IsRegistrable, IsMakeable, IsHookable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->id = Str::slug($this->id);
        $this->setHook('admin_init', [$this, 'register']);
        $this->hook();
    }


    // =========================================================================
    // Hooking
    // =========================================================================

    final public function register(): void {
        if (empty($this->id) || empty($this->title) || empty($this->menuPage)) {
            return;
        }

        add_settings_section(
            $this->id,
            $this->title,
            [$this, 'render'],
            $this->menuPage,
            $this->args
        );
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->id($identifier);
    }

    /**
     * Sets the id of the settings section.
     *
     * @param string $id The id of the section.
     *
     * @return static
     */
    final public function id(string $id): static {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the title of the settings section.
     *
     * @param string $title The title of the section.
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the callback function for rendering the section.
     *
     * @param Closure|string|array $callback The callback function or method.
     *
     * @return static
     */
    final public function callback(Closure|string|array $callback): static {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Sets the slug of the menu page associated with this section.
     *
     * @param string $pageSlug The slug of the menu page.
     *
     * @return static
     */
    final public function page(string $pageSlug): static {
        $this->menuPage = $pageSlug;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    final public function getIdentifier(): string {
        return $this->id;
    }

    /**
     * Gets the id of the settings section.
     *
     * @return string
     */
    final public function getId(): string {
        return $this->id;
    }

    /**
     * Gets the title of the settings section.
     *
     * @return string
     */
    final public function getTitle(): string {
        return $this->title;
    }

    /**
     * Gets the slug of the menu page associated with this section.
     *
     * @return string
     */
    final public function getPage(): string {
        return $this->menuPage;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Renders the settings section and invokes the provided callback.
     * May be overridden in subclasses to provide custom rendering logic.
     *
     * @return void
     */
    public function render(): void {
        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }
    }
}