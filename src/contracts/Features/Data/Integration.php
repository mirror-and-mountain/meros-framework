<?php

namespace MM\Meros\Contracts\Features\Data;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Admin\MenuPage;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\MakesItems;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Facades\Admin\SettingsContainers;

abstract class Integration extends Feature implements Registrable {
    /**
     * The name of the integration, which is used as its identifier.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The label of the integration.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The description of the integration.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The category of the integration.
     *
     * @var string
     */
    protected string $category = 'general';

    /**
     * The authentication type of the integration.
     *
     * @var string
     */
    protected string $authType = 'api_key';

    /**
     * The integration's available environments.
     *
     * @var array
     */
    protected array $environments = [];

    /**
     * Whether the integration is enabled or not.
     *
     * @var boolean
     */
    protected bool $enabled;

    /**
     * The menu page associated with the integration, if any.
     *
     * @var MenuPage|null
     */
    private ?MenuPage $menuPage = null;

    /**
     * The settings container associated with the integration, if any.
     *
     * @var SettingsContainer|null
     */
    private ?SettingsContainer $settingsContainer = null;

    use IsRegistrable, MakesItems, InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->resolveSettingsContainer();
    }

    protected function whenConfigured(): void {
        if (empty($this->name)) {
            throw new \RuntimeException('Integration name must be set.');
        }

        if (empty($this->label)) {
            $this->label = Str::title(Str::replace('_', ' ', $this->name));
        }
    }

    private function resolveSettingsContainer(): void {
        $containerName = 'meros_integration_settings_' . $this->getName(true);
        $container = SettingsContainers::get($containerName, $this->getProvider());

        if ($container === null) {
            $container = $this->makeItem(SettingsContainer::class, function ($container) use ($containerName) {
                $container->name($containerName);
            });
        }

        if (!($container instanceof SettingsContainer)) {
            throw new \RuntimeException('The settings container for the integration must implement the SettingsContainer interface.');
        }

        $this->settingsContainer = $container;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->name($identifier);
    }

    /**
     * Sets the integration's name, which is used as its identifier.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $this->name = Str::snake(Str::replace('-', '_', $name));
        return $this;
    }

    /**
     * Sets the integration's label.
     *
     * @param string $label
     *
     * @return static
     */
    final public function label(string $label): static {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the integration's description.
     *
     * @param string $description
     *
     * @return static
     */
    final public function description(string $description): static {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the integration's category.
     *
     * @param string $category
     *
     * @return static
     */
    final public function category(string $category): static {
        $this->category = $category;
        return $this;
    }

    /**
     * Sets the integration's authentication type.
     *
     * @param string $authType
     *
     * @return static
     * @throws \InvalidArgumentException If the provided auth type is invalid.
     */
    final public function authType(string $authType): static {
        if (!in_array($authType, ['api_key', 'oauth2', 'none'])) {
            throw new \InvalidArgumentException('Invalid auth type: ' . $authType);
        }

        $this->authType = $authType;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    final public function getIdentifier(): string {
        return $this->name;
    }

    /**
     * Retrieves the integration's name, which is used as its identifier.
     *
     * @param boolean $slug Whether to return the name as a slug. Defaults to false.
     *
     * @return string
     */
    final public function getName(bool $slug = false): string {
        return $slug ? Str::replace('_', '-', $this->name) : $this->name;
    }

}