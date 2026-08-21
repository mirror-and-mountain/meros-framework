<?php 

namespace MM\Meros\Registers\Assets;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Register;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Contracts\Features\Assets\AssetGroup;
use MM\Meros\Facades\Support\Registers;

abstract class Assets extends Register implements RegistrarMaker {
    /**
     * The contract classname of the asset type that this register manages.
     * Should be set in implementing classes.
     *
     * @var string
     */
    protected string $assetContract = '';

    /**
     * The contract classname of the dependency group used by this register.
     * Should be set in implementing classes.
     *
     * @var string
     */
    protected string $dependencyGroupContract = '';

    use IsRegistrarMaker;

    final protected function configure(): void {
        $this->unique(true);
        $this->contract($this->assetContract);
        $this->preloadType('instance');
    }

    /**
     * Resolves the dependency group for this register, creating it if it does not already exist.
     *
     * @param string|array $assetsPathOrClass Optional. An asset path or classname, or an array of assets configuration to add to the dependency group.
     * @param string       $handle            Optional. The handle to use for the asset if a single path or classname is provided.
     *
     * @return AssetGroup
     */
    final public function dependencies(string|array $assetsPathOrClass = '', string $handle = ''): AssetGroup {
        if (empty($this->dependencyGroupContract)) {
            throw new \Exception('The dependency group contract has not been set for this register (' . static::class . ').');
        }

        $this->ensureCheckout('dependencies');
        $provider       = $this->getProvider();
        $providerHandle = $provider->getHandle();
        $providerName   = $provider->getName();
        $contract       = $this->dependencyGroupContract;

        $register = Registers::getRegisterFor(AssetGroup::class);

        if (!$register) {
            throw new \Exception('No register found for the AssetGroup contract.');
        }

        $dependencyArea = Str::before(
            Str::snake(class_basename($contract))
        , '_');

        $groupName = "{$providerHandle}_{$dependencyArea}_dependencies";
        $group     = $register->checkout($provider)->get($groupName);

        if ($group !== null) {
            return $group;
        }

        $groupDescription = ucfirst($dependencyArea) . " dependencies for {$providerName}.";

        // Register the dependency group
        $register->checkout($provider)->register($contract, $groupName);

        // Make the dependency group and set its name and description
        $group = $register
            ->checkout($provider)
            ->makeFrom($contract, function (AssetGroup $group) use ($groupName, $groupDescription, $assetsPathOrClass, $handle) {
                $group->name($groupName);
                $group->description($groupDescription);

                if (!empty($assetsPathOrClass)) {
                    $group->add($assetsPathOrClass, $handle);
                }
            });

        return $group;
    }

    /**
     * Resolves the dependency group for this register, creating it if it does not already exist.
     * 
     * Shorthand alias for the `dependencies()` method.
     *
     * @param string|array $assetsPathOrClass Optional. An asset path or classname, or an array of assets configuration to add to the dependency group.
     * @param string       $handle            Optional. The handle to use for the asset if a single path or classname is provided.
     *
     * @return AssetGroup
     */
    final public function deps(string|array $assetsPathOrClass = '', string $handle = ''): AssetGroup {
        return $this->dependencies($assetsPathOrClass, $handle);
    }
}