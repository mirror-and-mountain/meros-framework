<?php 

namespace MM\Meros\Contracts\Features\Admin;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Concerns\IsHookable;
use MM\Meros\Contracts\Features\Components\Field;

final class SettingsField {
    /**
     * The field's id.
     *
     * @var string
     */
    private string $id = '';

    /**
     * The field's title.
     *
     * @var string
     */
    private string $title = '';

    /**
     * The slug of the menu page associated with this field
     * 
     * @var string
     */
    private string $pageSlug = '';

    /**
     * The id of the section associated with this field
     * 
     * @var string
     */
    private string $sectionId = 'default';

    /**
     * The position of the description relative to the field.
     * 
     * @var string
     */
    private string $descriptionPosition = 'before';

    /**
     * An array of additional arguments to for the field.
     *
     * @var array
     */
    private array $args = [];

    /**
     * The Setting instance associated with this SettingsField.
     *
     * @var Setting
     */
    private Setting $setting;

    /**
     * The Field instance associated with this SettingsField.
     *
     * @var Field|null
     */
    private ?Field $field = null;

    use IsHookable;

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    /**
     * Private constructor to enforce the use of the static make method.
     *
     * @param Setting $setting  The Setting instance to associate with this SettingsField.
     * @param Closure|null      $callback A callback function to configure the SettingsField instance.
     */
    private function __construct(Setting $setting, ?Closure $callback = null) {
        $this->setting = $setting;
        $this->init();

        if ($callback instanceof Closure) {
            $callback($this);
        }
    }

    /**
     * Initialises the SettingsField by setting and executing the hook.
     *
     * @return void
     */
    private function init(): void {
        $this->setHook('admin_init', [$this, 'register']);
        $this->hook();
    }

    /**
     * Creates a new instance of SettingsField with the provided Setting and callback.
     *
     * @param Setting      $setting  The Setting instance to associate with this SettingsField.
     * @param Closure|null $callback A callback function to configure the SettingsField instance.
     *
     * @return static
     */
    public static function make(Setting $setting, ?Closure $callback = null): static {
        return new static($setting, $callback);
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * The callback to be executed when the SettingsField is hooked.
     * 
     * Adds the settings field via the WordPress settings API, ensuring that the 
     * field is only added if all required properties are set.
     *
     * @return void
     */
    public function register(): void {
        if (empty($this->id) || empty($this->title) || empty($this->pageSlug)) {
            return;
        }

        if ($this->field === null) {
            return;
        }

        if ($this->sectionId === 'default') {
            add_settings_section(
                'default',
                '',
                '__return_null',
                $this->pageSlug
            ); // Ensure the default section exists
        }

        if ($this->descriptionPosition === 'before') {
            $this->placeDescriptionBeforeField();
            $this->field->description(''); // Clear the field's description to avoid duplication
        }

        $title = apply_filters('meros_settings_field_title', $this->title, $this->id, $this->setting);

        add_settings_field(
            $this->id,
            $title,
            [$this, 'render'],
            $this->pageSlug,
            $this->sectionId,
            $this->args
        );
    }

    /**
     * Filters the settings field title to include the description before the field if the description position is set to 'before'.
     *
     * @return void
     */
    private function placeDescriptionBeforeField(): void {
        add_filter('meros_settings_field_title', function ($title, $id, $setting) {
            $description = $setting->getDescription();
            if ($id === $this->id && $setting === $this->setting && !empty($description)) {
                $label = $this->field->isFieldSet()
                    ? '<span>' . $setting->getLabel() . '</span>'
                    : '<label for="' . esc_attr($id) . '">' . $setting->getLabel() . '</label>';

                return 
                    '<div class="meros-settings-field-title-wrapper">' .
                        $label .
                        '<div class="meros-settings-field-description">
                            <span class="description">
                                ' . $description . '
                            </span>
                        </div>
                    </div>';
            }

            return $title;
        }, 10, 3);
    }

    /**
     * Renders the field associated with this SettingsField.
     * 
     * If no field is associated, a message is displayed instead.
     *
     * @return void
     */
    public function render(): void {
        if ($this->field === null) {
            echo '<p>No field has been associated with this SettingsField.</p>';
            return;
        }
        
        $this->field->default($this->setting->getValue(true));
        echo $this->field->html();
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the id of the SettingsField.
     *
     * @param string $id The id to set.
     * @return static
     */
    public function id(string $id): static {
        $this->id = Str::slug($id);
        return $this;
    }

    /**
     * Sets the title of the SettingsField.
     *
     * @param string $title The title to set.
     * @return static
     */
    public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the position of the description relative to the field.
     *
     * @param string $position The position to set ('before' or 'after').
     * @return static
     */
    public function descriptionPosition(string $position): static {
        if (!in_array($position, ['before', 'after'])) {
            return $this; // Invalid position, do nothing
        }

        $this->descriptionPosition = $position;
        return $this;
    }

    /**
     * Sets the position of the description to after the field.
     *
     * @param boolean $after
     *
     * @return static
     */
    public function descriptionAfter(bool $after = true): static {
        $this->descriptionPosition = $after ? 'after' : 'before';
        return $this;
    }

    /**
     * Sets the slug of the menu page associated with this SettingsField.
     *
     * @param string $slug The slug to set.
     * @return static
     */
    public function page(string $slug): static {
        $this->pageSlug = $slug;
        return $this;
    }

    /**
     * Sets the id of the section associated with this SettingsField.
     *
     * @param string $id The section id to set.
     * @return static
     */
    public function section(string $id): static {
        $this->sectionId = Str::slug($id);
        return $this;
    }

    /**
     * Sets additional arguments for the SettingsField.
     *
     * @param array $args The arguments to set.
     * @return static
     */
    public function args(array $args): static {
        $this->args = $args;
        return $this;
    }

    /**
     * Associates a Field instance with this SettingsField.
     *
     * @param Field $field The Field instance to associate.
     * @return static
     */
    public function field(Field $field): static {
        $this->field = $field;
        $this->field->settingsField($this);

        $this->id = $field->getId();
        $this->title = $field->getLabel();

        if ($this->field->getDescription() !== '' && $this->setting->getDescription() === '') {
            $this->setting->description($this->field->getDescription());
        }

        return $this;
    }
}