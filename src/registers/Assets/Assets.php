<?php 

namespace MM\Meros\Registers\Assets;

use Closure;
use Illuminate\Support\Str;
use MM\Meros\Contracts\Register;

use MM\Meros\Contracts\Features\Assets\Asset;
use MM\Meros\Facades\Assets as AssetsFacade;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Contracts\Features\Assets\AssetGroup;
use MM\Meros\Contracts\Features\FeatureDefinition;

use MM\Meros\Registers\Assets\AssetGroups;
use MM\Meros\Facades\Support\Registers;

class Assets extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(Asset::class);
        $this->preloadType('instance');
        $this->identifierFormat('slug');
        $this->facade(AssetsFacade::class);
    }

    protected function allowAttach(FeatureDefinition $newFeature, FeatureDefinition $existingFeature): bool {
        if (!$newFeature instanceof Asset || !$existingFeature instanceof Asset) {
            return false;
        }

        return $newFeature->getArea() !== $existingFeature->getArea();
    }

    /**
     * Resolves an asset group via the given classname or alias, or makes a new one with an optional callback for configuration.
     *
     * @param Closure|string|null|null $callbackClassOrAlias
     *
     * @return AssetGroup
     */
    final public function group(Closure|string|null $callbackClassOrAlias = null): AssetGroup|AssetGroups {
        $this->ensureCheckout('group');
        $provider = $this->getProvider();
        $register = Registers::getRegisterFor(AssetGroup::class);

        if (is_string($callbackClassOrAlias) && !empty($callbackClassOrAlias)) {
            $classOrAlias   = $callbackClassOrAlias;
            $looksLikeClass = Str::contains($classOrAlias, '\\');

            if ($looksLikeClass) {
                $class = $classOrAlias;
                return $register->checkout($provider)->preload($class);
            }

            $alias = $classOrAlias;
            return $register->checkout($provider)->makeFrom($alias);
        }

        $callback = $callbackClassOrAlias ?? null;
        return $register->checkout($provider)->make($callback ?? []);
    }
}