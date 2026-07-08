<?php

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Collection;
use MM\Meros\Facades\DynamicChoiceSources;
use MM\Meros\Services\Contracts\Forms\DynamicChoiceSource;
use MM\Meros\Services\Registers\DynamicChoiceSources as DynamicChoiceSourcesRegister;

trait HasDynamicChoiceSources {
    /**
     * @return DynamicChoiceSource|DynamicChoiceSourcesRegister|Collection<int, DynamicChoiceSource>|null
     */
    protected function dynamicChoiceSources(string $source = '', ?Closure $callback = null): DynamicChoiceSource|DynamicChoiceSourcesRegister|Collection|null {
        if (empty($source)) {
            return DynamicChoiceSources::checkout($this);
        }

        $item = DynamicChoiceSources::checkout($this)->get($source, $callback);

        return $item instanceof DynamicChoiceSource ? $item : null;
    }
}
