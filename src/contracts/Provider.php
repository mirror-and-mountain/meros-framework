<?php 

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Support\Context;
use MM\Meros\Facades\Support\Context as ContextAccessor;

use MM\Meros\Support\ClassInfo;
use MM\Meros\Contracts\Concerns\ResolvesFeatureRequests;

abstract class Provider implements FeatureProvider {
    /**
     * An object containing several properties and methods that the feature provider
     * can utilise throughout its lifecycle.
     *
     * @var Context
     */
    final protected Context $context;

    private string $name = '';
    private string $handle = '';
    private string $description = '';
    private string $path = '';
    private string $uri = '';
    private string $author = '';
    private string $authorDescription = '';
    private string $authorUrl = '';
    private string $supportUrl = '';

    private array $defaultPreferences = [];
    private array $preferences = [];

    use ResolvesFeatureRequests;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final public function __construct() {
        $this->context = ContextAccessor::get();
        $this->setDefaultPreferences();
        $this->init();
        $this->afterInit();
    }

    abstract protected function init(): void;

    protected function afterInit(): void {
        // Intentionally left blank for child classes to override.
    }

    public function configure(): void {
        // Intentionally left blank for child classes to override.
    }

    public function whenConfigured(): void {
        // Intentionally left blank for child classes to override.
    }

    // =========================================================================
    // Identity Setters/Getters
    // =========================================================================

    /**
     * Sets the provider's name.
     *
     * @param string $name
     *
     * @return void
     */
    final protected function setName(string $name): void {
        $this->name = $name;

        if (empty($this->handle)) {
            $this->setHandle($name);
        }
    }

    /**
     * Sets the provider's handle.
     *
     * @param string $handle
     *
     * @return void
     */
    protected function setHandle(string $handle): void {
        $this->handle = Str::snake(Str::replace('-', '_', $handle));

        if (empty($this->name)) {
            $this->setName(Str::title(str_replace('_', ' ', $handle)));
        }
    }

    /**
     * Sets the provider's description.
     *
     * @param string $description
     *
     * @return void
     */
    final protected function setDescription(string $description): void {
        $this->description = $description;
    }

    /**
     * Sets the provider's path.
     *
     * @param string $path
     *
     * @return void
     */
    final protected function setPath(string $path): void {
        $this->path = \trailingslashit($path);
    }

    /**
     * Sets the provider's URI.
     *
     * @param string $uri
     *
     * @return void
     */
    final protected function setUri(string $uri): void {
        $this->uri = \trailingslashit($uri);
    }

    /**
     * Sets the provider's author.
     *
     * @param string $author
     *
     * @return void
     */
    final protected function setAuthor(string $author): void {
        $this->author = $author;
    }

    /**
     * Sets the provider's author description.
     *
     * @param string $authorDescription
     *
     * @return void
     */
    final protected function setAuthorDescription(string $authorDescription): void {
        $this->authorDescription = $authorDescription;
    }

    /**
     * Sets the provider's author URL.
     *
     * @param string $authorUrl
     *
     * @return void
     */
    final protected function setAuthorUrl(string $authorUrl): void {
        $this->authorUrl = $authorUrl;
    }

    /**
     * Sets the provider's support URL.
     *
     * @param string $supportUrl
     *
     * @return void
     */
    final protected function setSupportUrl(string $supportUrl): void {
        $this->supportUrl = $supportUrl;
    }

    /**
     * Retrieves the provider's handle.
     *
     * @param bool $slug Whether to return in slug-format. Defaults to false.
     * 
     * @return string
     */
    final public function getHandle(bool $slug = false): string {
        return $slug ? Str::slug(Str::replace('_', '-', $this->handle)) : $this->handle;
    }

    /**
     * Retrieves the provider's name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Retrieves the provider's description.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Retrieves the provider's path.
     * 
     * @param string $subPath Optional subpath to append to the provider's path.
     *
     * @return string
     */
    final public function getPath(string $subPath = ''): string {
        return $this->path . ltrim($subPath, '/');
    }

    /**
     * Retrieves the provider's URI.
     *
     * @param string $subUri Optional sub-URI to append to the provider's URI.
     *
     * @return string
     */
    final public function getUri(string $subUri = ''): string {
        return $this->uri . ltrim($subUri, '/');
    }

    /**
     * Retrieves the provider's author.
     *
     * @return string
     */
    final public function getAuthor(): string {
        return $this->author;
    }

    /**
     * Retrieves the provider's author description.
     *
     * @return string
     */
    final public function getAuthorDescription(): string {
        return $this->authorDescription;
    }

    /**
     * Retrieves the provider's author URL.
     *
     * @return string
     */
    final public function getAuthorUrl(): string {
        return $this->authorUrl;
    }

    /**
     * Retrieves the provider's support URL.
     *
     * @return string
     */
    final public function getSupportUrl(): string {
        return $this->supportUrl;
    }

    // =========================================================================
    // Preferences Management
    // =========================================================================

    /**
     * Sets the default preferences for the provider.
     *
     * @return void
     */
    protected function setDefaultPreferences(): void {
        $this->defaultPreferences = [
            'assets_path'         => 'resources/assets/wordpress/build', // No leading or trailing slashes
            'vite_assets_path'    => 'resources/assets/vite', // The default entry point for vite assets.
            'blocks_path'         => 'resources/blocks/build', // No leading or trailing slashes
            'components_path'     => 'src/app/View/Components', // No leading or trailing slashes
            'livewire_path'       => 'app/Livewire',
            'livewire_views_path' => 'resources/views/livewire', // No leading or trailing slashes
            'livewire_namespace'  => 'App\\Livewire', // The namespace for Livewire components
            'views_path'          => 'resources/views', // No leading or trailing slashes
            'routes_path'         => 'routes', // No leading or trailing slashes
            'tables_path'         => 'database/tables', // No leading or trailing slashes
        ];
    }

    /**
     * Sets preference using the given key and value.
     * Values must match the type of the default preference value to be set.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    protected function setPreference(string $key, mixed $value): void {
        $exists      = array_key_exists($key, $this->defaultPreferences);
        $typeMatches = gettype($value) === gettype($this->defaultPreferences[$key]);

        if ($exists && $typeMatches) {
            if (Str::endsWith($key, '_path')) {
                $value = trim($value, '/'); // Remove leading and trailing slashes for path preferences.
            }
            
            $this->preferences[$key] = $value;
        }

        else if (!$exists) {
            $this->preferences[$key] = $value;
        }
    }

    /**
     * Returns the value of a specific preference.
     *
     * @param string $key
     * @param bool   $fullPath Whether to return the full path (including the default path) or just the custom value set by the developer (only relavant for path preferences).
     *
     * @return mixed
     */
    final public function getPreference(string $key, bool $fullPath = true): mixed {
        if (Str::endsWith($key, '_path') && $fullPath) {
            
            if (isset($this->preferences[$key])) {
                return trailingslashit( $this->path ) . $this->preferences[$key];
            } 
            
            else if (isset($this->defaultPreferences[$key])) {
                return trailingslashit( $this->path ) . $this->defaultPreferences[$key];
            } 
            
            else {
                return null;
            }
        }

        if (Str::endsWith($key, '_url')) {

            if (isset($this->preferences[$key])) {
                return trailingslashit( $this->uri ) . $this->preferences[$key];
            } 
            
            else if (isset($this->defaultPreferences[$key])) {
                return trailingslashit( $this->uri ) . $this->defaultPreferences[$key];
            } 
            
            else {
                return null;
            }
        }
        
        return $this->preferences[$key] ?? $this->defaultPreferences[$key] ?? null;
    }

    // =========================================================================
    // Orchestration
    // =========================================================================

    /**
     * Creates and initialises an instance of the specified orchestrator class, passing the current provider instance to it.
     *
     * @param string $orchestratorClass
     *
     * @return void
     */
    final protected function orchestrate(string $orchestratorClass): void {
        $classInfo = ClassInfo::get($orchestratorClass);

        if ($classInfo->extends(Orchestrator::class)) {
            $orchestratorClass::create($this);
            return;
        }

        throw new \RuntimeException("The class '{$orchestratorClass}' does not extend the Orchestrator class.");
    }

    /**
     * Initialises an instance of the specified orchestrator class, passing the current provider instance to it.
     * Alias for the `orchestrate` method.
     *
     * @param string $orchestratorClass
     *
     * @return void
     */
    final protected function initialise(string $orchestratorClass): void {
        $this->orchestrate($orchestratorClass);
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the feature provider instance.
     *
     * @return static
     */
    public function get(): static {
        return $this;
    }

    /**
     * Returns the feature provider instance.
     *
     * @return FeatureProvider
     */
    public function getProvider(): FeatureProvider {
        return $this;
    }
}