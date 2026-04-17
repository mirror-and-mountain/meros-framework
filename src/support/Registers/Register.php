<?php 

namespace MM\Meros\App\Support\Registrars;

use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Feature;
use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\App\Support\Helpers\ClassInfo;

abstract class Register {
    /**
     * The property used to identify individual features within the register.
     *
     * @var string
     */
    protected string $identifier;

    /**
     * The fully qualified class name of the feature type that this register manages.
     *
     * @var string
     */
    protected string $itemClass;

    /**
     * The collection of features registered in this register.
     *
     * @var Collection
     */
    protected Collection $items;

    /**
     * Register constructor.
     *
     * @param FeatureProvider $provider The feature provider that owns this register.
     */
    public function __construct(
        protected FeatureProvider $provider
    ) {
        $this->items = collect([]);
    }

    /**
     * Adds a feature to the appropriate collection in the register.
     *
     * @param Feature $feature The feature to add.
     * 
     * @return Feature The feature that was added.
     */
    public function add(Feature $feature): Feature {
        $this->items->push($feature);
        return $feature;
    }

    /**
     * Attaches an existing feature class to the register.
     *
     * @param string $class The fully qualified class name of the feature to attach.
     * 
     * @return Feature The feature instance that was attached.
     *
     * @throws \InvalidArgumentException If the provided class does not extend the expected item class.
     */
    public function attach(string $class): Feature {
        $classInfo = ClassInfo::get($class);

        if (!$classInfo->extends($this->itemClass)) {
            throw new \InvalidArgumentException("Class {$class} must extend {$this->itemClass} to be attached to the register.");
        }

        return $this->add(new $this->itemClass(
            $this->provider
        ));
    }

    /**
     * Retrieves a feature or collection of features from the register.
     *
     * @param string|null $id Optional identifier to retrieve a specific feature.
     * 
     * @return Collection|Feature|null The requested feature(s) or null if not found.
     */
    public function get(?string $id = null): Collection|Feature|null {
        if ($id) {
            return $this->items->firstWhere($this->identifier, $id);
        }

        return $this->items;
    }

     /**
     * Creates a new feature and adds it to the register.
     *
     * @param array $props Arguments for the feature's constructor.
     *
     * @return Feature The newly created feature instance.
     */
    public function make(array $props = []): Feature {
        $parsedProperties = $this->parseProperties($props);

        return $this->add(new $this->itemClass(
            $this->provider,
            $parsedProperties
        ));
    }

    /**
     * Configures an existing feature in the register using a callback.
     *
     * @param string   $id       The identifier of the feature to configure.
     * @param callable $callback A callback that receives the feature instance for configuration.
     * 
     * @return Feature|null The configured feature instance or null if not found.
     */
    public function configure(string $id, callable $callback): ?Feature {
        $feature = $this->get($id);

        if ($feature) {
            if (method_exists($feature, 'configure')) {
                $feature->configure($callback);
            } else {
                $callback($feature);
            }
            return $feature;
        }

        return null;
    }

    /**
     * Parses the properties for creating a new feature instance.
     *
     * @param array $props The properties to parse.
     * 
     * @return array The parsed properties ready for the feature's constructor.
     */
    abstract protected function parseProperties(array $props): array;
}